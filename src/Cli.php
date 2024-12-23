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

        $options->registerOption('really', 'Actually do the import, without this option only a dryrun is done');
    }

    protected function main(Options $options)
    {
        $config = new Config(
            $options->getArgs()[0],
            $options->getArgs()[1],
            $options->getArgs()[2],
            $this,
            !$options->getOpt('really')
        );

        if ($config->isDryrun()) {
            $this->success('Dryrun only, no changes will be made');
        } else {
            $config->getDatabase()->beginTransaction();
        }

        try {
            $importer = new Importer($config);
            $importer->import();
            if (!$config->isDryrun()) {
                $config->getDatabase()->commit();
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            if (!$config->isDryrun()) {
                $config->getDatabase()->rollBack();
            }
            return 1;
        }
        return 0;
    }

}
