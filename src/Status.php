<?php

namespace App;

class Status
{
    protected string $origin;
    protected string $id;
    protected string $language;
    protected string $content;
    protected \DateTime $published;
    protected ?string $cw = null;
    /** @var Tag[] */
    protected array $tags = [];
    /** @var Attachment[] */
    protected array $attachments = [];
    protected Thread $thread;
    protected ?Status $replyParent = null;

    protected Config $config;


    public function __construct(Config $config, array $data)
    {
        $this->config = $config;

        // main status handling
        $this->origin = $data['object']['id'];
        $this->published = new \DateTime($data['object']['published']);
        $this->id = $this->config->getUlid()->generate($this->published->getTimestamp() * 1000);
        if ($data['object']['contentMap'] ?? false) {
            $this->language = array_keys($data['object']['contentMap'])[0];
            $this->content = array_values($data['object']['contentMap'])[0];
        } else {
            $this->language = 'en';
            $this->content = $data['object']['content'];
        }

        $this->thread = Thread::getInstance($this, $data['object']['inReplyTo'] ?? null);
        if($data['object']['inReplyTo'] ?? false) {
            $this->replyParent = $this->thread->getOtherStatus($data['object']['inReplyTo']);
        }

        // content warning
        if ($data['object']['sensitive'] ?? false) {
            $this->cw = $data['object']['summary'] ?? 'cw';
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

        $this->thread->save();

        $record = [
            'id' => $this->id,
            'created_at' => $this->published->format('c'),
            'updated_at' => $this->published->format('c'),
            'uri' => $this->getNewUri(),
            'url' => $this->getNewUrl(),
            'content' => $this->content,
            'attachments' => json_encode($attachments),
            'tags' => json_encode($tags),
            'mentions' => null,
            'emojis' => null,
            'local' => 1,
            'account_id' => $this->config->getUserid(),
            'account_uri' => $this->config->getProto() . '://' . $this->config->getInstance() . '/users/' . $this->config->getUsername(),
            'in_reply_to_id' => $this->replyParent?->getId(),
            'in_reply_to_uri' => $this->replyParent?->getNewUri(),
            'in_reply_to_account_id' => $this->replyParent ? $this->getConfig()->getUserid() : null, // FIXME for now we only have our own replies
            'boost_of_id' => null,
            'boost_of_account_id' => null,
            'content_warning' => $this->cw,
            'visibility' => 'public',
            'sensitive' => $this->cw ? 1 : 0,
            'language' => $this->language,
            'created_with_application_id' => null, // FIXME do we need it? Should we add the importer as an application?
            'activity_streams_type' => 'Note',
            'text' => strip_tags($this->content),
            'federated' => 1,
            'pinned_at' => null,
            'fetched_at' => null,
            'poll_id' => null,
            'thread_id' => $this->thread->getId(),
            'interaction_policy' => null, // we might want to block interactions on imported statuses
            'pending_approval' => 0,
            'approved_by_uri' => null,
        ];

        $this->config->getDatabase()->saveRecord('statuses', $record);
    }

    public function getNewUri(): string
    {
        return $this->config->getProto() . '://' . $this->config->getInstance() .
            '/users/' . $this->config->getUsername() . '/statuses/' . $this->id;
    }

    public function getNewUrl(): string
    {
        return $this->config->getProto() . '://' . $this->config->getInstance() .
            '/@' . $this->config->getUsername() . '/statuses/' . $this->id;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getOrigin(): string
    {
        return $this->origin;
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
