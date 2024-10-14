<?php

/**
 * Outputs a string with a specific color.
 *
 * @param string $text The text to be colored.
 * @param int $color The color code.
 *
 * Available color codes:
 * 30 - black
 * 31 - red
 * 32 - green
 * 33 - yellow
 * 34 - blue
 * 35 - magenta
 * 36 - cyan
 * 37 - white
 * 90 - bright black
 * 91 - bright red
 * 92 - bright green
 * 93 - bright yellow
 * 94 - bright blue
 * 95 - bright magenta
 * 96 - bright cyan
 * 97 - bright white
 */
function constyle($text="", $color=0) {
    return "\033[" . $color . "m" . $text . "\033[0m";
}

function clear_line() {
    echo "\r\033[K";
    echo "\r";
}

function clear_console() {
    echo "\033c\033[3J";
}

function get_single_char() {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows implementation using PowerShell
        $char = shell_exec('powershell -command "[console]::readkey().keychar"');
        return $char[0]; // Return only the first character
    } else {
        // Unix-like implementation
        system('stty -echo');
        system('stty cbreak');
        $char = fgetc(STDIN);
        system('stty echo');
        system('stty -cbreak');
        return $char;
    }
}