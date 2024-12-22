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

        $record = [
            'id' => $this->id,
            'created_at' => $this->parent->getPublished()->format('c'),
            'updated_at' => $this->parent->getPublished()->format('c'),
            'status_id' => $this->parent->getId(),
//            'url' => ,
            'remote_url' => null,
            'account_id' => $this->parent->getConfig()->getUserid(),
            'description' => $this->description,
            'scheduled_status_id' => null,
            'blurhash' => $this->blurhash,
            'processing' => 2, // FIXME what does this mean?
            'avatar' => 0,
            'header' => 0,
            'cached' => 1,
            'original_width' => $this->width,
            'original_height' => $this->height,
            'original_size' => filesize($this->localFile),
            'original_aspect' => $this->aspect,
            'small_width' => $this->thumbWidth,
            'small_height' => $this->thumbHeight,
            'small_size' => $this->thumbFile ? filesize($this->thumbFile) : 0,
            'small_aspect' => $this->aspect,
            'focus_x' => $this->focus_x,
            'focus_y' => $this->focus_y,
//            'file_path' => ,
            'file_content_type' => $this->content_type,
            'file_file_size' => filesize($this->localFile),
//            'thumbnail_path' => ,
            'thumbnail_content_type' => $this->content_type,
//            'thumbnail_file_size' => ,
//            'thumbnail_url' => ,
//            'thumbnail_remote_url' => ,
//            'original_duration' => ,
//            'original_framerate' => ,
//            'original_bitrate' => ,
            'type' => 1, // FIXME what does this mean?
        ];

        $this->parent->getConfig()->getDatabase()->saveRecord('media_attachments', $record);
    }


    protected function readImageInfo($data)
    {
        try {
            [$width, $height, $type] = getimagesize($this->localFile);
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

    protected function createThumbnail()
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
        } else {
            $this->thumbFile = null;
            $this->thumbWidth = null;
            $this->thumbHeight = null;
        }

    }

    protected function extension()
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

    protected function blurhash(): string
    {
        $image = imagecreatefromstring(file_get_contents($this->localFile));
        $width = imagesx($image);
        $height = imagesy($image);

        $pixels = [];
        for ($y = 0; $y < $height; ++$y) {
            $row = [];
            for ($x = 0; $x < $width; ++$x) {
                $index = imagecolorat($image, $x, $y);
                $colors = imagecolorsforindex($image, $index);

                $row[] = [$colors['red'], $colors['green'], $colors['blue']];
            }
            $pixels[] = $row;
        }

        $components_x = 4;
        $components_y = 3;
        return Blurhash::encode($pixels, $components_x, $components_y);
    }

    protected function originalPath(): string
    {
        $conf = $this->parent->getConfig();

        return $conf->getInstanceDir() . '/' . $conf->getUserid() .
            '/attachment/original/' .
            $this->id . '.' . $this->extension();
    }

    public function getId(): string
    {
        return $this->id;
    }
}
