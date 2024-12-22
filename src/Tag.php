<?php

namespace App;

class Tag
{
    protected Status $parent;
    protected string $name;
    protected string $id;

    public function __construct(Status $parent, array $data)
    {
        $this->id = $parent->getConfig()->getUlid()->generate(
            $parent->getPublished()->getTimestamp() * 1000
        );
        $this->parent = $parent;
        $this->name = ltrim($data['name'], '#');

        $href = $data['href']; // old URL
        $url = 'https://' . $this->parent->getConfig()->getInstance() . '/tags/' . $this->name; // new URL

        // update the URL in the status' content
        $content = $this->parent->getContent();
        $content = str_replace('href="' . $href . '"', 'href="' . $url . '"', $content);
        $this->parent->setContent($content);
    }

    public function save(): void
    {
        $record = $this->parent->getConfig()->getDatabase()->queryRecord(
            'SELECT id FROM tags WHERE name = ?',
            [$this->name]
        );
        if ($record) {
            // tag exists, update the ID
            $this->id = $record['id'];
        } else {
            $record = [
                'id' => $this->id,
                'created_at' => $this->parent->getPublished()->format('c'),
                'updated_at' => $this->parent->getPublished()->format('c'),
                'name' => $this->name,
                'useable' => 1,
                'listable' => 1,
            ];
            $this->parent->getConfig()->getDatabase()->saveRecord('tags', $record);
        }

        $linkrecord = [
            'status_id' => $this->parent->getId(),
            'tag_id' => $this->id,
        ];
        $this->parent->getConfig()->getDatabase()->saveRecord('status_tags', $linkrecord);
    }

    public function getId(): string
    {
        return $this->id;
    }
}
