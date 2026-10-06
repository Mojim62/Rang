<?php
/**
 * Guard-aware duplicate function declaration detector.
 *
 * WordPress loads plugins BEFORE the theme. If two files declare the same
 * top-level function without a `function_exists()` guard, PHP aborts with a
 * fatal "Cannot redeclare" error. This scanner finds such collisions while
 * correctly ignoring declarations that ARE wrapped in a function_exists guard.
 *
 * Safety rule (regression-tested in tests/regression/test_duplicate_detector.sh):
 * a function declared in MORE THAN ONE file is safe ONLY if EVERY declaring
 * file guards its own declaration with function_exists(). A guard present in
 * one file does NOT neutralize an unguarded declaration in another file: if
 * load order ever flips (CLI bootstrap, test harness, a future third copy),
 * the unguarded side reintroduces the fatal "Cannot redeclare" error.
 *
 * Exit 0 = clean, Exit 1 = unsafe duplicate(s) found.
 */
$root = $argv[1] ?? '.';
$skip = array('/docs/', '/.git/', '/node_modules/');

$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$funcs = array();   // lowercased name => list of files
$sources = array(); // path => source text

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
    $sources[$path] = $src;
    $tokens = token_get_all($src);
    $n = count($tokens);

    for ($i = 0; $i < $n; $i++) {
        if (is_array($tokens[$i]) && $tokens[$i][0] === T_FUNCTION) {
            $j = $i + 1;
            while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            // Skip by-reference marker "&"
            if ($j < $n && $tokens[$j] === '&') {
                $j++;
                while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
            }
            if ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $name = strtolower($tokens[$j][1]);
                $funcs[$name][] = $path;
            }
        }
    }
}

$fail = 0;
foreach ($funcs as $name => $files) {
    $files = array_values(array_unique($files));
    if (count($files) < 2) {
        continue;
    }
    // Safe ONLY if EVERY declaring file has its own function_exists() guard
    // for this name. Any unguarded declaring file makes the set unsafe.
    $unguarded = array();
    foreach ($files as $f) {
        $src = $sources[$f];
        if (!preg_match('/function_exists\s*\(\s*[\'"]' . preg_quote($name, '/') . '[\'"]\s*\)/i', $src)) {
            $unguarded[] = $f;
        }
    }
    if (empty($unguarded)) {
        echo "GUARDED (safe): {$name} declared in " . count($files) . " files (function_exists guard present in ALL files)\n";
    } else {
        echo "UNSAFE DUPLICATE: {$name} — unguarded declaration in:\n";
        foreach ($unguarded as $f) {
            echo "   - " . str_replace($root, '', $f) . "\n";
        }
        $fail++;
    }
}

if ($fail > 0) {
    echo "FAIL: {$fail} unsafe duplicate function declaration(s).\n";
    exit(1);
}
echo "OK: no unsafe duplicate function declarations.\n";
exit(0);
