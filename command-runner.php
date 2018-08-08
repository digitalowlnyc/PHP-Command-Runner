<?php 

/**
 * Usage: php command-runner.php composer.json <command-number>
 *
 * This will read the "scripts" key from composer.json and run the command number
 * provided (first command is "1").
 *
 * What this command runner does:
 * - Creates a dated log file and puts it in command-runner-logs/
 * - Records the command run and timestamp in command-runner-logs/command-runner.log
 * 
 */

    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, $errno, 1, $errfile, $errline);
    });

	if(!file_exists($argv[1])) {
		echo "File does not exist: " . $argv[1] . PHP_EOL;
		exit(1);
	}

    $filename = $argv[1];

    if(count($argv) < 3) {
        throw new \RuntimeException("Must provided command number (1-...) to run");
    }

    $commandIndexToRun = $argv[2];

    function parseCommandsFromPlainTextFile($filename) {
        $lines = file_get_contents($filename);

        $lines = explode(PHP_EOL, $lines);

        $now = time();
        $runAt = "D" . date("Y_m_d", $now) . "_T" . date("H_i_s", $now);

        $commands = [];
        foreach($lines as $line) {
            if(empty(trim($line))) {
                continue;
            }

            $commands[] = $line;
        }
        
        return $commands;
    }

    function parseCommandsFromComposerFile($filename) {
        $lines = file_get_contents($filename);

        $data = json_decode($lines, true);
        
        $commandDefinitions = $data["scripts"];
        
        $commands = [];
        foreach($commandDefinitions as $commandName => $commandArray) {
            if(count($commandArray) > 1) {
                throw new RunTimeException("Only supports one command per key right now");
            }
            
            $command = $commandArray[0];
            
            if(strpos($command, "@php ") !== false) {
                $command = str_replace("@php", "php", $command);
            }
            
            $commands[] = $command;
        }

        return $commands;
    }

    echo "Reading commands from " . $filename . PHP_EOL;

    if($filename === "composer.json") {
        $commands = parseCommandsFromComposerFile($filename);
    } else {
        $commands = parseCommandsFromPlainTextFile($filename);
    }

    $now = time();
    $runAtTimestamp = "D" . date("Y_m_d", $now) . "_T" . date("H_i_s", $now);

	echo "Commands available: " . PHP_EOL;

    foreach($commands as $i => $command) {
        echo ($i + 1) . ": " . $command . PHP_EOL;
    }

    echo PHP_EOL;

    $commandIndex = $commandIndexToRun - 1;

    if($commandIndex < 0 || $commandIndex > count($commands)) {
        throw new RuntimeException("Invalid command number: " . $commandIndexToRun . ", valid values are " . "1 to " . count($commands));
    }

    $commandsSelectedToRun = [$commands[$commandIndex]];

    echo "Will run the following commands: " . PHP_EOL;
    print_r($commandsSelectedToRun);
    
    $logDirectory = "command-runner-logs";
    if(!file_exists($logDirectory)) {
        mkdir($logDirectory);
    } else {
        if(!is_dir($logDirectory)) {
            throw new RuntimeException("Log directory exists but is not a directory!");
        }
    }

    $commandRunnerLog = $logDirectory . "/command-runner.log";

	foreach($commandsSelectedToRun as $commandIndex => $command) {
        echo "Running command: " . $command . PHP_EOL;
		sleep(5);

		$logFileName = $logDirectory . "/log_" . $runAtTimestamp . "_command_" . $commandIndex . ".log";

		$fullCommand = $command . " 2>&1 | tee -a " . $logFileName;

        $commandRunTime = date("Y-m-d H:i:s");
		$logHeader = "Command: " . $command . PHP_EOL;
		$logHeader .= "Running command at: " . $commandRunTime . PHP_EOL;
		$logHeader .= "Full command: " . $fullCommand . PHP_EOL;

		echo "Running command #" . $commandIndex . ": " . $command . " (log file=" . $logFileName . ")". PHP_EOL;

		file_put_contents($logFileName, $logHeader);
        
        $retVal = null;
		system($fullCommand, $retVal);
        
        file_put_contents($commandRunnerLog, $commandRunTime . ": " . $fullCommand . PHP_EOL, FILE_APPEND);
        
        if($retVal !== 0) {
            echo "Non-zero exit status " . $retVal . " on command: " . $command . PHP_EOL;
            die();
        }
	}