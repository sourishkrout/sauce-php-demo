<?php

function getFiles($glob, $base_dir)
{
    $test_files = array();
    $files = glob(joinPaths($base_dir, $glob), GLOB_MARK);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            if ($file[strlen($file)-1] === '/') {
                $test_files = array_merge($test_files, getFiles('*', $file));
            } else {
                $test_files[] = $file;
            }
        }
    }
    return $test_files;
}

function joinPaths() {
    $paths = array_filter(func_get_args());
    return preg_replace('#/{2,}#', '/', implode('/', $paths));
}


function getTestMethodsFromFile($file)
{
    $methods = array();
    $file_str = file_get_contents($file);
    preg_match_all("/function (test[^\(]+)\(/", $file_str, $matches, PREG_PATTERN_ORDER);
    foreach ($matches[1] as $match) {
        $methods[] = $match;
    }
    return $methods;
}

function runTestSets($all_tests, $num_procs)
{
    echo "\n";
    $outputs = array();
    $active_procs = array();
    $active_pipes = array();
    $n_tests = $n_assertions = $n_errors = 0;
    $errors = array();
    $start_time = time();
    while (count($all_tests) || count($active_procs)) {
        if (count($active_pipes))
            collectStreamOutput($active_pipes, $outputs);
        if (count($active_procs)) {
            $stats = updateProcessStatus($active_procs, $active_pipes, $outputs);
            $n_tests += $stats[0];
            $n_assertions += $stats[1];
            $n_errors += $stats[2];
            foreach($stats[3] as $error)
                $errors[] = $error;
        }
        if (count($active_procs) < $num_procs && count($all_tests))
            startNewProcess($all_tests, $active_procs, $active_pipes, $outputs);
    }
    $n_secs = time() - $start_time;
    echo "\n\n";
    foreach ($errors as $i => $error) {
        $num = $i+1;
        echo "$num) $error\n\n";
    }
    echo "Time: $n_secs seconds\n\n";
    echo "Tests: $n_tests, Assertions: $n_assertions, Errors: $n_errors\n\n";
}

function collectStreamOutput($active_pipes, &$outputs)
{
    $out_streams = array();
    foreach ($active_pipes as $pipes) {
        $out_streams[] = $pipes[1];
    }
    $e = NULL; $f = NULL;
    $num_changed = stream_select($out_streams, $e, $f, 0, 200000);
    if ($num_changed) {
        foreach ($out_streams as $changed_stream) {
            foreach ($active_pipes as $proc_id => $pipes) {
                if ($changed_stream === $pipes[1]) {
                    $outputs[$proc_id] .= stream_get_contents($changed_stream);
                }
            }
        }
    }
}


function updateProcessStatus(&$active_procs, &$active_pipes, $outputs)
{
    $total_tests = $total_assertions = $total_errors = 0;
    $errors = array();
    foreach ($active_procs as $id => $proc) {
        $status = proc_get_status($proc);
        if (!$status['running']) {
            //echo "Command {$status['command']} finished\n";
            fclose($active_pipes[$id][0]);
            fclose($active_pipes[$id][1]);
            fclose($active_pipes[$id][2]);
            proc_close($proc);
            $stats = handleOutput($id, $outputs[$id]);
            list($n_tests, $n_assertions, $n_errors, $errors) = $stats;
            unset($active_procs[$id]);
            unset($active_pipes[$id]);
            $total_tests += $n_tests;
            $total_assertions += $n_assertions;
            $total_errors = $n_errors;
        }
    }
    return array($total_tests, $total_assertions, $total_errors, $errors);
}

function handleOutput($id, $output)
{
    $n_tests = $n_assertions = $n_errors = 0;
    $errors = array();
    // first, find dots, Es, etc...
    preg_match("/^[\.FESI]+$/m", $output, $matches);
    if (count($matches))
        echo $matches[0];

    preg_match("/^OK \(([0-9]+) tests?, ([0-9]+) assertions/m", $output, $matches);
    if (count($matches) == 3) {
        $n_tests = intval($matches[1]);
        $n_assertions = intval($matches[2]);
    } else {
        // echo "Could not find OK tests\n";
        // print_r($matches);
    }

    preg_match("/^Tests: ([0-9]+), Assertions: ([0-9]+), Errors: ([0-9]+)/m", $output, $matches);
    if (count($matches) == 4) {
        $n_tests += intval($matches[1]);
        $n_assertions += intval($matches[2]);
        $n_errors += intval($matches[3]);
    } else {
        // echo "Could not find Failed tests\n";
        // print_r($matches);
    }

    preg_match("/^There ((was)|(were)) [0-9]+ ((error)|(failure)).+\n\n(.+)\nFAIL/Ums", $output, $matches);
    if (count($matches) == 8) {
        $error_str = trim($matches[7]);
        $split_errors = preg_split("/^[0-9]+\) /m", $error_str);
        foreach ($split_errors as $error) {
            if (trim($error))
                $errors[] = trim($error);
        }
    } else {
        // echo "Could not find errors\n";
        // print_r($matches);
    }
    return array($n_tests, $n_assertions, $n_errors, $errors);
}

function startNewProcess(&$all_tests, &$active_procs, &$active_pipes, &$outputs)
{
    list($proc, $pipes) = getProcessForTest(array_pop($all_tests));
    $proc_id = uniqid();
    $status = proc_get_status($proc);
    //echo "Running {$status['command']}\n";
    $active_procs[$proc_id] = $proc;
    $active_pipes[$proc_id] = $pipes;
    $outputs[$proc_id] = '';
}

function getProcessForTest($test)
{
    $dspec = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w"),
        2 => array("pipe", "w"),
    );
    list($testName, $testFile) = $test;
    $cmd = "phpunit --filter=$testName $testFile";
    $process = proc_open($cmd,
        $dspec,
        $pipes,
        NULL);
    stream_set_blocking($pipes[1], 0);
    stream_set_blocking($pipes[2], 0);

    return array($process, $pipes);
}


function main($argv)
{
    $usage = "Usage: parallel.php -p[NUM_PROCESSES] --path=TEST_PATH/TEST_GLOB\n";
    if (!isset($argv[1]) || !$argv[1])
        die($usage);

    $opts = getopt("p::", array('path:'));
    if (!isset($opts['path']))
        die($usage);
    if (isset($opts['p']) && intval($opts['p']) > 0)
        $processes = intval($opts['p']);
    else
        $processes = 1;

    $files = getFiles($opts['path'], dirname(__FILE__));

    $all_tests = array();
    foreach($files as $file) {
        $methods = getTestMethodsFromFile($file);
        foreach($methods as $method) {
            $all_tests[] = array($method, $file);
        }
    }

    runTestSets($all_tests, $processes);
}

if (isset($argv[0]) && $argv[0])
{
    main($argv);
}
