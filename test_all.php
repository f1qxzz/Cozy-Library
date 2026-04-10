<?php
$dir = new RecursiveDirectoryIterator(__DIR__);
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
$errors = [];
foreach($files as $file) {
    $filePath = $file[0];
    // try to load it ? NO, that executes it. We can read it and check for common missing things, but syntax check needs `php -l`.
    // Let's use internal php_check_syntax if available, or just use exec/shell_exec with PHP path from $_SERVER['_'] if we can.
    $output = [];
    $return_var = 0;
    // Assuming php is in PATH for the CGI, or we can find it:
    exec(PHP_BINARY . " -l \"" . $filePath . "\"", $output, $return_var);
    if ($return_var !== 0) {
        $errors[] = implode("\n", $output);
    }
}
if (empty($errors)) {
    echo "NO_ERRORS";
} else {
    echo implode("\n", $errors);
}
?>
