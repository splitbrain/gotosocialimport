<?php

namespace App;

class Importer
{

    public function __construct(
        protected Config $config,
    )
    {
    }

    public function import()
    {
        $data = json_decode(file_get_contents($this->config->getMastodonJson()), true, 512, JSON_THROW_ON_ERROR);

        foreach ($data['orderedItems'] as $item) {
            if ($item['type'] !== 'Create') continue;
            if ($item['object']['type'] !== 'Note') continue; // polls would be 'Question'
            if (!in_array('https://www.w3.org/ns/activitystreams#Public', array_merge($item['to'], $item['cc']))) continue;
            foreach ($item['object']['tag'] as $tag) {
                if ($tag['type'] === 'Mention') continue 2; // skip mentions
                // FIXME theoretically we could keep them without creating an account and mention entry... would be simple links then?
            }

            $status = new Status($this->config, $item);
            $status->save();
        }

        if(!$this->config->isDryrun()) {
            // update account statistics
            $sql = 'UPDATE account_stats SET statuses_count = (SELECT COUNT(*) FROM statuses WHERE account_id = ?) WHERE account_id = ?';
            $this->config->getDatabase()->execute($sql, [$this->config->getUserid(), $this->config->getUserid()]);
            $sql = 'UPDATE account_stats SET last_status_at = (SELECT MAX(created_at) FROM statuses WHERE account_id = ?) WHERE account_id = ?';
            $this->config->getDatabase()->execute($sql, [$this->config->getUserid(), $this->config->getUserid()]);

            // update join date
            $sql = 'UPDATE accounts SET created_at = (SELECT MIN(created_at) FROM statuses WHERE account_id = ?) WHERE id = ?';
            $this->config->getDatabase()->execute($sql, [$this->config->getUserid(), $this->config->getUserid()]);
        }
    }

}
