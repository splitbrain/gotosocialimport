<?php

namespace App;


use splitbrain\slika\Slika;

class Attachment
{
    protected Status $parent;

    protected string $id;
    protected string $content_type;
    protected string $description;
    protected string $localFile; // in export directory
    protected ?string $blurhash;
    protected ?int $width;
    protected ?int $height;
    protected ?float $aspect;
    protected int $focus_x;
    protected int $focus_y;
    protected ?string $thumbFile;
    protected ?int $thumbWidth;
    protected ?int $thumbHeight;

    public function __construct(Status $parent, array $data)
    {
        $this->parent = $parent;

        $this->id = $this->parent->getConfig()->getUlid()->generate(
            $this->parent->getPublished()->getTimestamp() * 1000
        );
        $this->content_type = $data['mediaType'];
        $this->localFile = $this->parent->getConfig()->getMastodonDir() . '/' . $data['url'];
        $this->description = $data['name'] ?? '';
        $this->blurhash = $data['blurhash'] ?? null;

        if (isset($data['focalPoint'])) {
            $this->focus_x = $data['focalPoint'][0];
            $this->focus_y = $data['focalPoint'][1];
        } else {
            $this->focus_x = 0;
            $this->focus_y = 0;
        }

        $this->readImageInfo($data);
        $this->createThumbnail();

    }

    public function save()
    {
        $this->copyFiles();

        $record = [
            'id' => $this->id,
            'created_at' => $this->parent->getPublished()->format('c'),
            'updated_at' => $this->parent->getPublished()->format('c'),
            'status_id' => $this->parent->getId(),
            'url' => $this->newUrl('original'),
            'remote_url' => null,
            'account_id' => $this->parent->getConfig()->getUserid(),
            'description' => $this->description,
            'scheduled_status_id' => null,
            'blurhash' => $this->blurhash,
            'processing' => $this->thumbFile ? 2 : 0, // 2 = processed, 0 = not processed, no thumbnail available
            'avatar' => 0,
            'header' => 0,
            'cached' => 1,
            'original_width' => $this->width,
            'original_height' => $this->height,
            'original_size' => $this->width * $this->height, // pixels
            'original_aspect' => $this->aspect,
            'small_width' => $this->thumbWidth,
            'small_height' => $this->thumbHeight,
            'small_size' => $this->thumbWidth * $this->thumbHeight, // pixels
            'small_aspect' => $this->aspect,
            'focus_x' => $this->focus_x,
            'focus_y' => $this->focus_y,
            'file_path' => $this->newPath('original'),
            'file_content_type' => $this->content_type,
            'file_file_size' => filesize($this->localFile),
            'thumbnail_path' => $this->thumbFile ? $this->newPath('small') : null,
            'thumbnail_content_type' => $this->content_type,
            'thumbnail_file_size' => $this->thumbFile ? filesize($this->thumbFile) : 0,
            'thumbnail_url' => $this->thumbFile ? $this->newUrl('small') : null,
            'thumbnail_remote_url' => null,
            'original_duration' => 0, // FIXME we currently only support images
            'original_framerate' => 0, // FIXME we currently only support images
            'original_bitrate' => 0, // FIXME we currently only support images
            'type' => $this->gtsFileType(),
        ];

        $this->parent->getConfig()->getDatabase()->saveRecord('media_attachments', $record);
    }


    protected function readImageInfo(array $data): void
    {
        try {
            [$width, $height, $type] = getimagesize($this->localFile);
            if (!$width || !$height) {
                throw new \Exception('Could not read image size');
            }
            $this->width = $width;
            $this->height = $height;
            $this->content_type = image_type_to_mime_type($type);
        } catch (\Exception $e) {
            $this->width = $data['width'] ?? null;
            $this->height = $data['height'] ?? null;
        }

        if ($this->width && $this->height) {
            $this->aspect = $this->width / $this->height;
        } else {
            $this->aspect = null;
        }
    }

    protected function createThumbnail(): void
    {
        try {
            if (!file_exists($this->localFile . '.thumb')) {
                Slika::run($this->localFile)->resize(512, 512)->save($this->localFile . '.thumb');
            }
        } catch (\Exception $e) {
            $this->parent->getConfig()->getLogger()->error('Could not create thumbnail for ' . $this->localFile);
        }
        if (file_exists($this->localFile . '.thumb')) {
            $this->thumbFile = $this->localFile . '.thumb';
            [$this->thumbWidth, $this->thumbHeight] = getimagesize($this->thumbFile);
            if (!$this->thumbWidth || !$this->thumbHeight) {
                $this->thumbFile = null; // invalid thumb
            }
        } else {
            $this->thumbFile = null;
            $this->thumbWidth = null;
            $this->thumbHeight = null;
        }

    }

    protected function extension(): string
    {
        switch ($this->content_type) {
            case 'image/jpeg':
                return 'jpeg';
            case 'image/png':
                return 'png';
            case 'image/gif':
                return 'gif';
            case 'video/mp4':
                return 'mp4';
            case 'audio/mpeg':
                return 'mp3';
            default:
                return 'bin';
        }
    }

    /**
     * Theoretically there's also type 4 for short videos, but we don't support that
     */
    protected function gtsFileType(): int
    {
        if (str_starts_with($this->content_type, 'image/')) {
            return 1;
        } elseif (str_starts_with($this->content_type, 'audio/')) {
            return 2;
        } elseif (str_starts_with($this->content_type, 'video/')) {
            return 3;
        } else {
            return 0; // unknown
        }
    }

    protected function newPath(string $type, bool $fullPath = false): string
    {
        if (!in_array($type, ['original', 'small'])) {
            throw new \RuntimeException('Invalid type');
        }

        $conf = $this->parent->getConfig();
        $path = $conf->getUserid() .
            '/attachment/' . $type . '/' .
            $this->id . '.' . $this->extension();

        if ($fullPath) {
            $path = $conf->getInstanceDir() . '/' . $path;
        }

        return $path;
    }

    protected function newUrl(string $type): string
    {
        if (!in_array($type, ['original', 'small'])) {
            throw new \RuntimeException('Invalid type');
        }


        $conf = $this->parent->getConfig();
        return $conf->getProto() . '://' . $conf->getInstance() . '/fileserver/' . $conf->getUserid() .
            '/attachment/' . $type . '/' .
            $this->id . '.' . $this->extension();
    }

    protected function copyFiles(): void
    {
        $conf = $this->parent->getConfig();;

        foreach (['original', 'small'] as $type) {
            $dst = $this->newPath($type, true);
            $src = $type === 'original' ? $this->localFile : $this->thumbFile;
            if (!$src) continue;

            if ($conf->isDryrun()) {
                $conf->getLogger()->info("copy file \n" . print_r(['src' => $src, 'dst' => $dst], true));
            } else {
                if (!file_exists(dirname($dst))) {
                    mkdir(dirname($dst), 0755, true);
                }
                copy($src, $dst);
            }
        }
    }

    public function getId(): string
    {
        return $this->id;
    }
}
