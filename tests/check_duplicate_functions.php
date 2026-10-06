<?php
/**
 * Per-declaration guard-aware duplicate function detector.
 *
 * WordPress loads plugins BEFORE the theme. If two files declare the same
 * top-level function, the outcome depends on guard placement:
 *
 *   - all declarations unguarded       -> PHP fatal "Cannot redeclare" (unsafe)
 *   - all declarations guarded        -> safe (first file to load wins)
 *   - MIXED (guarded + unguarded)      -> UNSAFE: whether a fatal occurs depends
 *                                        entirely on file load order. A guard in
 *                                        one file does NOT neutralize an
 *                                        unguarded declaration in another file.
 *
 * Guard detection is per-declaration and structural: a declaration counts as
 * guarded only if it sits directly inside an
 * `if ( ! function_exists('name') ) {` block. A bare usage such as
 * `function_exists('name') ? name() : ''` elsewhere in the file does NOT count
 * as a guard (a previous file-level regex version made that mistake).
 *
 * Exit 0 = clean, exit 1 = unsafe duplicate/mixed declaration(s) found.
 */
$root = $argv[1] ?? '.';
$skip = array('/docs/', '/.git/', '/node_modules/');

$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$decls = array();   // lowercased name => list of array('file' => path, 'guarded' => bool)

foreach ($rii as $file) {
    if ($file->isDir()) {
        continue;
    }
    $path = $file->getPathname();
    if (substr($path, -4) !== '.php') {
        continue;
    }
    $skipIt = false;
    foreach ($skip as $s) {
        if (strpos($path, $s) !== false) {
            $skipIt = true;
            break;
        }
    }
    if ($skipIt) {
        continue;
    }

    $src = file_get_contents($path);
    if ($src === false) {
        continue;
    }
    $tokens = token_get_all($src);
    $n = count($tokens);
    $offset = 0;

    for ($i = 0; $i < $n; $i++) {
        $tok = $tokens[$i];
        $tokLen = is_array($tok) ? strlen($tok[1]) : strlen($tok);
        if (!is_array($tok) || $tok[0] !== T_FUNCTION) {
            $offset += $tokLen;
            continue;
        }
        // Collect the declared name (skip whitespace and "&").
        $j = $i + 1;
        while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }
        if ($j < $n && $tokens[$j] === '&') {
            $j++;
            while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
        }
        if ($j >= $n || !is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING) {
            // Anonymous function (closure): not a redeclaration candidate.
            $offset += $tokLen;
            continue;
        }
        $name = strtolower($tokens[$j][1]);

        // Structural guard check: the ~200 bytes of source immediately before
        // the `function` keyword must end with the guard block opening:
        //   ... function_exists( 'name' ) ) {<whitespace>
        $window = substr($src, max(0, $offset - 200), 200);
        $guarded = (bool) preg_match(
            '/function_exists\s*\(\s*[\'"]' . preg_quote($name, '/') . '[\'"]\s*\)\s*\)\s*\{\s*$/i',
            $window
        );
        $decls[$name][] = array('file' => $path, 'guarded' => $guarded);
        $offset += $tokLen;
    }
}

$fail = 0;
foreach ($decls as $name => $list) {
    // Same-file duplicates are always fatal in PHP.
    $perFile = array();
    foreach ($list as $d) {
        $perFile[$d['file']] = ($perFile[$d['file']] ?? 0) + 1;
    }
    $files = array_keys($perFile);

    $guardedCount = 0;
    foreach ($list as $d) {
        if ($d['guarded']) {
            $guardedCount++;
        }
    }
    $allGuarded = ($guardedCount === count($list));
    $allUnguarded = ($guardedCount === 0);

    $multiInOneFile = false;
    foreach ($perFile as $cnt) {
        if ($cnt > 1) {
            $multiInOneFile = true;
        }
    }

    if (count($files) < 2 && !$multiInOneFile) {
        continue; // single declaration: nothing to check
    }

    if ($multiInOneFile) {
        echo "UNSAFE SAME-FILE REDECLARE: {$name}\n";
        foreach ($files as $f) {
            echo "   - " . str_replace($root, '', $f) . "\n";
        }
        $fail++;
        continue;
    }

    if ($allGuarded) {
        echo "SAFE (all guarded): {$name} declared in " . count($files) . " files\n";
        continue;
    }

    if ($allUnguarded) {
        echo "UNSAFE DUPLICATE (no guards): {$name}\n";
    } else {
        echo "UNSAFE MIXED GUARD (order-dependent fatal): {$name}\n";
        echo "   guarded in {$guardedCount}/" . count($list) . " declarations — an unguarded copy in ANY file fatals if that file loads second\n";
    }
    foreach ($files as $f) {
        echo "   - " . str_replace($root, '', $f) . "\n";
    }
    $fail++;
}

if ($fail > 0) {
    echo "FAIL: {$fail} unsafe function declaration pattern(s).\n";
    exit(1);
}
echo "OK: no unsafe duplicate function declarations.\n";
exit(0);
