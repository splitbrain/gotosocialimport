<?php

namespace App;

class Status
{
    protected string $id;
    protected string $language;
    protected string $content;
    protected \DateTime $published;
    /** @var Tag[] */
    protected array $tags = [];
    /** @var Attachment[] */
    protected array $attachments = [];

    protected Config $config;


    public function __construct(Config $config, array $data)
    {
        $this->config = $config;

        // main status handling
        $this->published = new \DateTime($data['object']['published']);
        $this->id = $this->config->getUlid()->generate($this->published->getTimestamp() * 1000);
        if ($data['object']['contentMap'] ?? false) {
            $this->language = array_keys($data['object']['contentMap'])[0];
            $this->content = array_values($data['object']['contentMap'])[0];
        } else {
            $this->language = 'en';
            $this->content = $data['object']['content'];
        }

        // tags
        foreach ($data['object']['tag'] as $tag) {
            if ($tag['type'] !== 'Hashtag') continue;
            $this->tags[] = new Tag($this, $tag);
        }

        // media
        foreach ($data['object']['attachment'] as $attachment) {
            if ($attachment['type'] !== 'Document') continue;
            $this->attachments[] = new Attachment($this, $attachment);
        }
    }

    public function save(): void
    {
        $attachments = [];
        foreach ($this->attachments as $attachment) {
            $attachment->save();
            $attachments[] = $attachment->getId();
        }

        $tags = [];
        foreach ($this->tags as $tag) {
            $tag->save();
            $tags[] = $tag->getId();
        }

        $record = [
            'id' => $this->id,
            'created_at' => $this->published->format('c'),
            'updated_at' => $this->published->format('c'),
            'uri' => 'https://' . $this->config->getInstance() . '/users/' . $this->config->getUsername() . '/statuses/' . $this->id,
            'url' => 'https://' . $this->config->getInstance() . '@' . $this->config->getUsername() . '/statuses/' . $this->id,
            'content' => $this->content,
            'attachments' => json_encode($attachments),
            'tags' => json_encode($tags),
            'mentions' => null,
            'emojis' => null,
            'local' => 1,
            'account_id' => $this->config->getUserid(),
            'account_uri' => 'https://' . $this->config->getInstance() . '/users/' . $this->config->getUsername(),
            'in_reply_to_id' => null,
            'in_reply_to_uri' => null,
            'in_reply_to_account_id' => null,
            'boost_of_id' => null,
            'boost_of_account_id' => null,
            'content_warning' => null, // FIXME needs to be set from export
            'visibility' => 'public',
            'sensitive' => 0, // FIXME what is this?
            'language' => $this->language,
            'created_with_application_id' => null, // FIXME do we need it? Should we add the importer as an application?
            'activity_streams_type' => 'Note',
            'text' => '', // FIXME do we want to run a strip_tags on content?
            'federated' => 1,
            'pinned_at' => null,
            'fetched_at' => null,
            'poll_id' => null,
            'thread_id' => null, // FIXME we need to create threads
            'interaction_policy' => null, // we might want to block interactions on imported statuses
            'pending_approval' => 0,
            'approved_by_uri' => null,
        ];

        $this->config->getDatabase()->saveRecord('statuses', $record);
    }


    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getPublished(): \DateTime
    {
        return $this->published;
    }

}
