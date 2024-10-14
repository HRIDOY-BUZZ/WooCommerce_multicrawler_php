<?php
    ini_set('memory_limit', '1024M');
    ini_set('max_execution_time', 0);

    include 'console_text.php';
    include 'extra_functions.php';
    include 'secondary_functions.php';
    include 'primary_functions.php';

    function pause() {
        echo "\nPress any key to continue...";
        $char = get_single_char();
    }

START:

    clear_console();
    //Display Intro
    echo constyle("\n\t\tSHOPIFY WEB MULTI-CRAWLER\n\n", 96);

    // Display MENU
    echo constyle(constyle("\n\t\t\t[MENU]:\n\n", 96), 4);
    echo constyle(
        "\t\t1.\tRun Full Crawler\n".
        "\t\t2.\tContinue from Part-1\n".
        "\t\t3.\tRun Part-1 Only\n".
        "\t\t4.\tRun Part-2 Only\n".
        "\t\t0.\tExit\n\n", 
    93);

INPUT:
    // Get user input
    echo constyle("\t\tEnter your choice: ", 92);
    $choice = get_single_char();
    echo $choice . "\n";

    // Perform action based on user input
    switch ($choice) {
        case '1':
            clear_console();
            $p1 = part1(); 

            if($p1) {
                echo "\t" . constyle("DONE!", 92) . "\n\n";
                $p2 = part2();
                if($p2) {
                    echo "\t" . constyle("DONE!", 92) . "\n\n";
                } else {
                    echo "\t" . constyle("PART-2 FAILED TO EXECUTE!", 91) . "\n\n";
                }
            } else {
                echo "\t" . constyle("PART-1 FAILED TO EXECUTE! PART-2 IGNORED...", 91) . "\n\n";
            }
            
            pause();
            goto START; break;

        case '2':
            clear_console();
            echo "\t" . constyle("[CONTINUE FROM PART-1]", 92) . "\n\n";
            $p1 = part1(true); 

            if($p1) {
                echo "\t" . constyle("DONE!", 92) . "\n\n";
                $p2 = part2();
                if($p2) {
                    echo "\t" . constyle("DONE!", 92) . "\n\n";
                } else {
                    echo "\t" . constyle("PART-2 FAILED TO EXECUTE!", 91) . "\n\n";
                }
            } else {
                echo "\t" . constyle("PART-1 FAILED TO EXECUTE! PART-2 IGNORED...", 91) . "\n\n";
            }
            
            pause();
            goto START; break;

        case '3':
            clear_console();
            $p = part1();

            if($p) {
                echo "\t" . constyle("DONE!", 92) . "\n\n";
            } else {
                echo "\t" . constyle("PART-1 FAILED TO EXECUTE!", 91) . "\n\n";
            }

            pause();
            goto START; break;

        case '4':
            clear_console();
            $p = part2(); 
            
            if($p) {
                echo "\t" . constyle("DONE!", 92) . "\n\n";
            } else {
                echo "\t" . constyle("PART-2 FAILED TO EXECUTE!", 91) . "\n\n";
            }

            pause();
            goto START; break;

        case '0':
            exit;

        default:
            echo "\t\tInvalid choice. Please try again.\n\n";
            goto INPUT; break;
    }

    goto START;
?>