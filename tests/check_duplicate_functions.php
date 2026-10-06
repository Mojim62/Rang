<?php
/**
 * Guard-aware duplicate function declaration detector.
 *
 * WordPress loads plugins BEFORE the theme. If two files declare the same
 * top-level function without a `function_exists()` guard, PHP aborts with a
 * fatal "Cannot redeclare" error. This scanner finds such collisions while
 * correctly ignoring declarations that ARE wrapped in a function_exists guard.
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
    // A name is safe ONLY if EVERY declaring file wraps its own declaration
    // in a function_exists() guard. A guard in one file does NOT make an
    // unguarded declaration in another file safe: load order decides which
    // one fatals, and any unguarded duplicate is a latent fatal error.
    $unguarded = array();
    foreach ($files as $f) {
        $src = $sources[$f];
        if (!preg_match('/function_exists\s*\(\s*[\'"]' . preg_quote($name, '/') . '[\'"]\s*\)/i', $src)) {
            $unguarded[] = $f;
        }
    }
    if (empty($unguarded)) {
        echo "GUARDED (safe): {$name} declared in " . count($files) . " files (function_exists guard present in every declaring file)\n";
    } else {
        echo "UNSAFE DUPLICATE: {$name} declared in " . count($files) . " files; unguarded in:\n";
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