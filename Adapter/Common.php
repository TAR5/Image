<?php

namespace Gregwar\Image\Adapter;

abstract class Common extends Adapter
{
    /**
     * {@inheritdoc}
     */
    public function zoomCrop($width, $height, $background = 'transparent', $xPosLetter = 'center', $yPosLetter = 'center')
    {
        $originalWidth = $this->width();
        $originalHeight = $this->height();

        // Calculate the different ratios
        $originalRatio = $originalWidth / $originalHeight;
        $newRatio = $width / $height;

        // Compare ratios
        if ($originalRatio > $newRatio) {
            // Original image is wider
            $newHeight = $height;
            $newWidth = (int) $height * $originalRatio;
        } else {
            // Equal width or smaller
            $newHeight = (int) $width / $originalRatio;
            $newWidth = $width;
        }

        // Perform resize
        $this->resize($newWidth, $newHeight, $background, true);

        // Define x position
        switch ($xPosLetter) {
            case 'L':
            case 'left':
                $xPos = 0;
                break;
            case 'R':
            case 'right':
                $xPos = (int) $newWidth - $width;
                break;
            case 'center':
                $xPos = (int) ($newWidth - $width) / 2;
                break;
            default:
                $factorW = $newWidth / $originalWidth;
                $xPos = $xPosLetter * $factorW;

                // If the desired cropping position goes beyond the width then
                // set the crop to be within the correct bounds.
                if ($xPos + $width > $newWidth) {
                    $xPos = (int) $newWidth - $width;
                }
        }

        // Define y position
        switch ($yPosLetter) {
            case 'T':
            case 'top':
                $yPos = 0;
                break;
            case 'B':
            case 'bottom':
                $yPos = (int) $newHeight - $height;
                break;
            case 'center':
                $yPos = (int) ($newHeight - $height) / 2;
                break;
            default:
                $factorH = $newHeight / $originalHeight;
                $yPos = $yPosLetter * $factorH;

                // If the desired cropping position goes beyond the height then
                // set the crop to be within the correct bounds.
                if ($yPos + $height > $newHeight) {
                    $yPos = (int) $newHeight - $height;
                }
        }

        // Crop image to reach desired size
        $this->crop($xPos, $yPos, $width, $height);

        return $this;
    }

    /**
     * Resizes the image forcing the destination to have exactly the
     * given width and the height.
     *
     * @param int $w  the width
     * @param int $h  the height
     * @param int $bg the background
     */
    public function forceResize($width = null, $height = null, $background = 'transparent')
    {
        return $this->resize($width, $height, $background, true);
    }

    /**
     * {@inheritdoc}
     */
    public function scaleResize($width = null, $height = null, $background = 'transparent', $crop = false)
    {
        return $this->resize($width, $height, $background, false, true, $crop);
    }

    /**
     * {@inheritdoc}
     */
    public function cropResize($width = null, $height = null, $background = 'transparent')
    {
        return $this->resize($width, $height, $background, false, false, true);
    }

    /**
     * Read exif rotation from file and apply it.
     */
    public function fixOrientation()
    {
        if (!in_array(exif_imagetype($this->source->getInfos()), array(
            IMAGETYPE_JPEG,
            IMAGETYPE_TIFF_II,
            IMAGETYPE_TIFF_MM,
        ))) {
            return $this;
        }

        if (!extension_loaded('exif')) {
            throw new \RuntimeException('You need to EXIF PHP Extension to use this function');
        }

        $exif = @exif_read_data($this->source->getInfos());

        if ($exif === false || !array_key_exists('Orientation', $exif)) {
            return $this;
        }

        return $this->applyExifOrientation($exif['Orientation']);
    }

    /**
     * Apply orientation using Exif orientation value.
     */
    public function applyExifOrientation($exif_orienation)
    {
        switch ($exif_orienation) {
            case 1:
                break;

            case 2:
                $this->flip(false, true);
                break;

            case 3: // 180 rotate left
                $this->rotate(180);
                break;

            case 4: // vertical flip
                $this->flip(true, false);
                break;

            case 5: // vertical flip + 90 rotate right
                $this->flip(true, false);
                $this->rotate(-90);
                break;

            case 6: // 90 rotate right
                $this->rotate(-90);
                break;

            case 7: // horizontal flip + 90 rotate right
                $this->flip(false, true);
                $this->rotate(-90);
                break;

            case 8: // 90 rotate left
                $this->rotate(90);
                break;
        }

        return $this;
    }

    /**
     * Opens the image.
     */
    abstract protected function openGif($file);

    abstract protected function openJpeg($file);

    abstract protected function openPng($file);

    abstract protected function openWebp($file);

    abstract protected function openAvif($file);

    /**
     * Creates an image.
     */
    abstract protected function createImage($width, $height);

    /**
     * Creating an image using $data.
     */
    abstract protected function createImageFromData($data);

    /**
     * Loading image from $resource.
     */
    protected function loadResource($resource)
    {
        $this->resource = $resource;
    }

    protected function loadFile($file, $type)
    {
        if (!$this->supports($type)) {
            throw new \RuntimeException('Type '.$type.' is not supported by GD');
        }

        if ($type == 'jpeg') {
            $this->openJpeg($file);
        }

        if ($type == 'gif') {
            $this->openGif($file);
        }

        if ($type == 'png') {
            $this->openPng($file);
        }

        if ($type == 'webp') {
            $this->openWebp($file);
        }

        if ($type == 'avif') {
            $this->openAvif($file);
        }

        if (false === $this->resource) {
            throw new \UnexpectedValueException('Unable to open file ('.$file.')');
        } else {
            $this->convertToTrueColor();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $source = $this->source;

        if ($source instanceof \Gregwar\Image\Source\File) {
            $this->loadFile($source->getFile(), $source->guessType());
        } elseif ($source instanceof \Gregwar\Image\Source\Create) {
            $this->createImage($source->getWidth(), $source->getHeight());
        } elseif ($source instanceof \Gregwar\Image\Source\Data) {
            $this->createImageFromData($source->getData());
        } elseif ($source instanceof \Gregwar\Image\Source\Resource) {
            $this->loadResource($source->getResource());
        } else {
            throw new \Exception('Unsupported image source type '.get_class($source));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function deinit()
    {
        $this->resource = null;
    }

    /**
     * {@inheritdoc}
     */
    public function resize($width = null, $height = null, $background = 'transparent', $force = false, $rescale = false, $crop = false)
    {
        $current_width = $this->width();
        $current_height = $this->height();
        $new_width = 0;
        $new_height = 0;
        $scale = 1.0;

        if ($height === null && preg_match('#^(.+)%$#mUsi', $width, $matches)) {
            $width = round($current_width * ((float) $matches[1] / 100.0));
            $height = round($current_height * ((float) $matches[1] / 100.0));
        }

        if (!$rescale && (!$force || $crop)) {
            if ($width != null && $current_width > $width) {
                $scale = $current_width / $width;
            }

            if ($height != null && $current_height > $height) {
                if ($current_height / $height > $scale) {
                    $scale = $current_height / $height;
                }
            }
        } else {
            if ($width != null) {
                $scale = $current_width / $width;
                $new_width = $width;
            }

            if ($height != null) {
                if ($width != null && $rescale) {
                    $scale = max($scale, $current_height / $height);
                } else {
                    $scale = $current_height / $height;
                }
                $new_height = $height;
            }
        }

        if (!$force || $width == null || $rescale) {
            $new_width = round($current_width / $scale);
        }

        if (!$force || $height == null || $rescale) {
            $new_height = round($current_height / $scale);
        }

        if ($width == null || $crop) {
            $width = $new_width;
        }

        if ($height == null || $crop) {
            $height = $new_height;
        }

        $this->doResize($background, (int) $width, (int) $height, (int) $new_width, (int) $new_height);
    }

    /**
     * Trim background color arround the image.
     *
     * @param int $bg the background
     */
    protected function _trimColor($background = 'transparent')
    {
        $width = $this->width();
        $height = $this->height();

        $b_top = 0;
        $b_lft = 0;
        $b_btm = $height - 1;
        $b_rt = $width - 1;

        //top
        for (; $b_top < $height; ++$b_top) {
            for ($x = 0; $x < $width; ++$x) {
                if ($this->getColor($x, $b_top) != $background) {
                    break 2;
                }
            }
        }

        // bottom
        for (; $b_btm >= 0; --$b_btm) {
            for ($x = 0; $x < $width; ++$x) {
                if ($this->getColor($x, $b_btm) != $background) {
                    break 2;
                }
            }
        }

        // left
        for (; $b_lft < $width; ++$b_lft) {
            for ($y = $b_top; $y <= $b_btm; ++$y) {
                if ($this->getColor($b_lft, $y) != $background) {
                    break 2;
                }
            }
        }

        // right
        for (; $b_rt >= 0; --$b_rt) {
            for ($y = $b_top; $y <= $b_btm; ++$y) {
                if ($this->getColor($b_rt, $y) != $background) {
                    break 2;
                }
            }
        }

        ++$b_btm;
        ++$b_rt;

        $this->crop($b_lft, $b_top, $b_rt - $b_lft, $b_btm - $b_top);
    }

    /**
     * Resizes the image to an image having size of $target_width, $target_height, using
     * $new_width and $new_height and padding with $bg color.
     */
    abstract protected function doResize($bg, int $target_width, int $target_height, int $new_width, int $new_height);

    /**
     * Gets the color of the $x, $y pixel.
     */
    abstract protected function getColor($x, $y);

    /**
     * {@inheritdoc}
     */
    public function enableProgressive()
    {
        throw new \Exception('The Adapter '.$this->getName().' does not support Progressive Image loading');
    }

    /**
     * This does nothing, but can be used to tag a ressource for instance (having a final image hash
     * for the cache different depending on the tag)
     */
    public function tag($tag)
    {
    }
}
