<?php
/**
 * Run the official EPV on an extracted install ZIP and fail on every finding.
 * Usage: php scripts/epv.php /path/to/epv /path/to/extracted-package
 */
if ($argc !== 3 || !is_file($argv[1] . '/vendor/autoload.php') || !is_dir($argv[2]))
{
    fwrite(STDERR, "Usage: php scripts/epv.php EPV_CHECKOUT EXTRACTED_PACKAGE\nInstall EPV dependencies first.\n");
    exit(2);
}

require $argv[1] . '/vendor/autoload.php';

try
{
    $output = new \Phpbb\Epv\Output\Output(new \Symfony\Component\Console\Output\ConsoleOutput(), false);
    $output->setFormatter(new \Phpbb\Epv\Output\OutputFormatter(false));
    new \Phpbb\Epv\Tests\TestStartup($output, \Phpbb\Epv\Tests\TestStartup::TYPE_DIRECTORY, $argv[2], false);
    // EPV's own CLI checks only fatal findings despite printing FAILED for
    // ordinary errors or warnings. Use its counters instead of parsing text.
    $findings = 0;
    foreach ([
        \Phpbb\Epv\Output\Output::FATAL,
        \Phpbb\Epv\Output\Output::ERROR,
        \Phpbb\Epv\Output\Output::WARNING,
        \Phpbb\Epv\Output\Output::NOTICE,
    ] as $severity)
    {
        $findings += $output->getMessageCount($severity);
    }
    exit($findings > 0 ? 1 : 0);
}
catch (\Throwable $error)
{
    fwrite(STDERR, "EPV could not complete: " . $error->getMessage() . "\n");
    exit(2);
}
