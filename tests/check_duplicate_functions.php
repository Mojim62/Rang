<?php
/**
 * Bamero duplicate function declaration detector (v2, guard-aware).
 *
 * Usage: php tests/check_duplicate_functions.php <repo-root>
 *
 * Soundness rules (regression-tested by tests/test_duplicate_detector.sh):
 *  - A declaration is GUARDED only if it is lexically inside the braced body
 *    of `if ( ! function_exists( 'name' ) ) {` for the SAME function name.
 *    A `function_exists()` call anywhere else in the file does NOT count.
 *  - A duplicate set is SAFE only if EVERY declaration of that name is guarded.
 *    A guarded declaration in one file does NOT make an unguarded declaration
 *    in another file safe.
 *  - Any unguarded duplicate is a fatal-error class defect => exit 1.
 *
 * Scans: all PHP files under wp-content/plugins and wp-content/themes
 * (excludes release-time dev dirs under any theme "tools" folder).
 */

if (PHP_SAPI !== 'cli' || !empty($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    exit('CLI only');
}
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

$root = isset($argv[1]) ? rtrim($argv[1], '/') : __DIR__;

$dirs = array(
    $root . '/wp-content/plugins',
    $root . '/wp-content/themes',
);

/** Gather declarations: name => list of [file, guarded] */
$decls = array();

foreach ($dirs as $base) {
    if (!is_dir($base)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    /** @var SplFileInfo $file */
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        // Release-time dev tooling is excluded from the artifact; skip scanning it.
        if (strpos($path, '/tools/') !== false) {
            continue;
        }
        foreach (scan_file($path) as $info) {
            $decls[$info['name']][] = array(
                'file'    => $path,
                'line'    => $info['line'],
                'guarded' => $info['guarded'],
            );
        }
    }
}

$fail = 0;
echo "Duplicate function declaration detector (guard-aware, v2)\n";

foreach ($decls as $name => $occurrences) {
    if (count($occurrences) < 2) {
        continue;
    }
    $allGuarded = true;
    $unguarded = array();
    foreach ($occurrences as $occ) {
        if (!$occ['guarded']) {
            $allGuarded = false;
            $unguarded[] = $occ;
        }
    }
    if ($allGuarded) {
        echo "  OK (guarded x" . count($occurrences) . "): {$name}\n";
        foreach ($occurrences as $occ) {
            echo "    - " . display_path($occ['file'], $root) . ":" . $occ['line'] . "\n";
        }
        continue;
    }
    $fail = 1;
    echo "  FATAL RISK: {$name} declared " . count($occurrences) . "x, unguarded in:\n";
    foreach ($unguarded as $occ) {
        echo "    - " . display_path($occ['file'], $root) . ":" . $occ['line'] . "\n";
    }
}

if (empty($decls)) {
    echo "  (no declarations found)\n";
}

if ($fail) {
    echo "RESULT: FAIL — unguarded duplicate declarations present\n";
    exit(1);
}
echo "RESULT: PASS\n";
exit(0);

function display_path($path, $root) {
    $rel = str_replace($root . '/', '', $path);
    return $rel !== $path ? $rel : $path;
}

/**
 * Token-walk a PHP file and return every top-level (depth==0 relative to the
 * function's own guard scope) `function name(...)` declaration with whether
 * it sits inside a matching `if ( ! function_exists('name') ) {` body.
 */
function scan_file($path) {
    $code = file_get_contents($path);
    if ($code === false) {
        return array();
    }
    $tokens = token_get_all(strip_php_strings_preserved($code));
    $n = count($tokens);

    $results = array();
    $depth = 0;          // brace depth in file scope
    // Stack of active guards: depth => function name guarded at that depth.
    $guards = array();

    for ($i = 0; $i < $n; $i++) {
        $tok = $tokens[$i];

        if (is_array($tok)) {
            list($id, $text) = $tok;
        } else {
            $id = null;
            $text = $tok;
        }

        if ($id === T_IF) {
            // Consume the condition up to the matching ')'.
            $paren = 0;
            $j = $i + 1;
            $cond = '';
            while ($j < $n) {
                $t = $tokens[$j];
                $tt = is_array($t) ? $t[1] : $t;
                if ($tt === '(') { $paren++; }
                elseif ($tt === ')') {
                    $paren--;
                    if ($paren === 0) { $j++; break; }
                }
                $cond .= $tt;
                $j++;
            }
            // Guard only if next significant token is '{'.
            $k = $j;
            while ($k < $n && is_array($tokens[$k]) && in_array($tokens[$k][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
                $k++;
            }
            $nt = $k < $n ? (is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k]) : '';
            if ($nt === '{') {
                if (preg_match("/!\s*function_exists\s*\(\s*['\"]([^'\"]+)['\"]\s*\)/i", $cond, $m)) {
                    $guards[$depth] = $m[1]; // guard body starts at current depth
                }
            }
            $i = $j - 1; // resume at token after ')'
            continue;
        }

        if ($id === T_FUNCTION) {
            // Find the function name.
            $j = $i + 1;
            $name = null;
            while ($j < $n) {
                $t = $tokens[$j];
                if (is_array($t) && $t[0] === T_WHITESPACE) { $j++; continue; }
                if (is_array($t) && $t[0] === T_STRING) { $name = $t[1]; }
                break;
            }
            if ($name !== null) {
                // Guarded iff the innermost active guard matches this name.
                $guarded = false;
                foreach ($guards as $gname) {
                    if ($gname === $name) { $guarded = true; break; }
                }
                $results[] = array(
                    'name' => $name,
                    'line' => is_array($tokens[$i]) ? $tokens[$i][2] : 0,
                    'guarded' => $guarded,
                );
            }
            continue;
        }

        if ($text === '{') {
            $depth++;
        } elseif ($text === '}') {
            $depth--;
            // A guard body opened at depth D closes when we return below D.
            if (isset($guards[$depth])) {
                unset($guards[$depth]);
            }
        }
    }
    return $results;
}

/**
 * Nothing fancy: token_get_all already preserves strings; identity kept for clarity.
 */
function strip_php_strings_preserved($code) {
    return $code;
}
