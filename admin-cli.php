<?php
require_once 'bootstrap.php';
/**
 * @psalm-suppress InvalidGlobal
 */
global $entityManager;

use GetOpt\ArgumentException;
use GetOpt\ArgumentException\Missing;
use GetOpt\GetOpt;
use GetOpt\Option;
use lolbot\cli_cmds;

$getOpt = new GetOpt();

$getOpt->addOption(Option::create('?', 'help', GetOpt::NO_ARGUMENT)->setDescription('Show this help and quit'));

//can probably use reflection here

$getOpt->addCommand(new cli_cmds\ignore_add());
$getOpt->addCommand(new cli_cmds\ignore_del());
$getOpt->addCommand(new cli_cmds\ignore_list());
$getOpt->addCommand(new cli_cmds\ignore_test());
$getOpt->addCommand(new cli_cmds\bot_add());
$getOpt->addCommand(new cli_cmds\bot_del());
$getOpt->addCommand(new cli_cmds\bot_list());
$getOpt->addCommand(new cli_cmds\network_add());
$getOpt->addCommand(new cli_cmds\network_list());

try {
    try {
        $getOpt->process();
    } catch (Missing $exception) {
        // catch missing exceptions if help is requested
        if (!$getOpt->getOption('help')) {
            throw $exception;
        }
    }
} catch (ArgumentException $exception) {
    file_put_contents('php://stderr', $exception->getMessage() . PHP_EOL);
    echo PHP_EOL . $getOpt->getHelpText();
    exit;
}

$command = $getOpt->getCommand();
if (!$command || $getOpt->getOption('help')) {
    echo $getOpt->getHelpText();
    exit;
}

// call the requested command
call_user_func($command->getHandler(), $getOpt);
