<?php

namespace App;

use splitbrain\phpcli\Options;
use splitbrain\phpcli\PSR3CLIv3;

class Cli extends PSR3CLIv3
{

    protected function setup(Options $options)
    {
        $options->setHelp('This script imports data from a Mastodon export into the database');

        $options->registerArgument('mastodondir', 'The directory with the Mastodon export, has the outbox.json file');
        $options->registerArgument('instancedir', 'The storage directory for the instance, has the sqlite database and media files');
        $options->registerArgument('account', 'The account to import into');
    }

    protected function main(Options $options)
    {
        $config = new Config(
            $options->getArgs()[0],
            $options->getArgs()[1],
            $options->getArgs()[2],
            $this
        );

        $importer = new Importer($config);
        $importer->import();
    }

}
