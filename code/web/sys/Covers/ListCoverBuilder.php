<?php
require_once ROOT_DIR . '/sys/Utils/StringUtils.php';
require_once ROOT_DIR . '/sys/Covers/CoverImageUtils.php';
require_once ROOT_DIR . '/RecordDrivers/GroupedWorkDriver.php';

class ListCoverBuilder{
    private $imageWidth = 280; //Pixels
    private $imageHeight = 400; // Pixels

    private $titleFont;
    private $authorFont;

    private $backgroundColor;

    public function __construct()
    {
        $this->titleFont = ROOT_DIR . '/fonts/JosefinSans-Bold.ttf';
        $this->authorFont = ROOT_DIR . '/fonts/JosefinSans-BoldItalic.ttf';
    }

    /**
     * @param string $title
     * @param array|null $listTitles
     * @param string $filename
     */
    public function getCover($title, $listTitles, $filename)
    {
        //Create the background image
        $imageCanvas = imagecreatetruecolor($this->imageWidth, $this->imageHeight);

        //Define our colors
        $white = imagecolorallocate($imageCanvas, 255, 255, 255);
        $this->setBackgroundColors($title);
        $backgroundColor = imagecolorallocate($imageCanvas, $this->backgroundColor['r'], $this->backgroundColor['g'], $this->backgroundColor['b']);

        //Draw a background for the entire image
        imagefilledrectangle($imageCanvas, 0, 0, $this->imageWidth, $this->imageHeight, $backgroundColor);

        //Draw a few overlapping covers from the list at the top of the cover
        $numCoversLoaded = 0;
        for ($i = min(count($listTitles) - 1, 3); $i >= 0 ; $i--){
            /** @var UserListEntry $curListEntry */
            $curListEntry = $listTitles[$i];

            $groupedWorkRecordDriver = new GroupedWorkDriver($curListEntry->groupedWorkPermanentId);
            if ($groupedWorkRecordDriver->isValid()){
                $bookcoverUrl = $groupedWorkRecordDriver->getBookcoverUrl('medium', true);
                //Load the cover
                if ($listEntryCoverImage = @file_get_contents($bookcoverUrl, false)) {
                    $listEntryImageResource = @imagecreatefromstring($listEntryCoverImage);

                    $listEntryWidth = imagesx($listEntryImageResource);
                    $listEntryHeight = imagesy($listEntryImageResource);

                    //Put a white background beneath the cover
                    $coverLeft = 10 + (40 * (3 - $i));
                    $coverTop = 10 + (35 * (3 - $i));
                    imagefilledrectangle($imageCanvas, $coverLeft, $coverTop, $listEntryWidth + $coverLeft, $listEntryHeight + $coverTop, $white);
                    if (imagecopyresampled( $imageCanvas, $listEntryImageResource, $coverLeft, $coverTop, 0, 0, $listEntryWidth, $listEntryHeight, $listEntryWidth, $listEntryHeight )){
                        $numCoversLoaded++;
                    }
                    imagedestroy($listEntryImageResource);
                }
            }
        }

        //Make sure the borders are preserved
        imagefilledrectangle($imageCanvas, $this->imageWidth - 10, 0, $this->imageWidth, $this->imageHeight, $backgroundColor);
        imagefilledrectangle($imageCanvas, 0, $this->imageWidth, $this->imageWidth -10, $this->imageHeight, $backgroundColor);

        $textColor = imagecolorallocate($imageCanvas, 50, 50, 50);

        imagefilledrectangle($imageCanvas, 10, $this->imageWidth, $this->imageWidth - 10, $this->imageHeight - 10, $white);
        imagerectangle($imageCanvas,  10, $this->imageWidth, $this->imageWidth - 10, $this->imageHeight - 10, $textColor);

        //Add the title at the bottom of the cover
        $this->drawText($imageCanvas, $title, $textColor);


        imagepng($imageCanvas, $filename);
        imagedestroy($imageCanvas);
    }

    private function setBackgroundColors($title)
    {
        $base_saturation = 100;
        $base_brightness = 90;
        $color_distance = 100;

        $counts = strlen($title);
        //Get the color seed based on the number of characters in the title and author.
        //We want a number from 10 to 360
        $color_seed = (int)_map(_clip($counts, 2, 80), 2, 80, 10, 360);

        $this->backgroundColor = ColorHSLToRGB(
            ($color_seed + $color_distance) % 360,
            $base_saturation,
            $base_brightness
        );
    }

    private function drawText($imageCanvas, $title, $textColor)
    {
        $title_font_size = $this->imageWidth * 0.09;

        $x = 15;
        $y = $this->imageWidth + 5;
        $width = $this->imageWidth - (30);
        //$height = $title_height;

        $title = StringUtils::trimStringToLengthAtWordBoundary($title, 60, true);
        /** @noinspection PhpUnusedLocalVariableInspection */
        list($totalHeight, $lines, $font_size) = wrapTextForDisplay($this->titleFont, $title, $title_font_size, $title_font_size * .15, $width, $this->imageHeight - $this->imageWidth - 20);
        addCenteredWrappedTextToImage($imageCanvas, $this->titleFont, $lines, $font_size, $font_size * .15, $x, $y, $this->imageWidth - 30, $textColor);
    }
}