<?php
/**
 * Read Time plugin for Craft CMS 5.x
 *
 * Calculate the estimated read time for content.
 *
 * @link      https://github.com/jalendport
 * @copyright Copyright (c) 2018 Jalen Davenport
 */

namespace jalendport\readtime\twigextensions;

use Craft;
use craft\elements\Entry;
use craft\errors\InvalidFieldException;
use craft\fields\Matrix;
use craft\helpers\StringHelper;
use jalendport\readtime\models\Settings;
use jalendport\readtime\ReadTime;
use jalendport\readtime\models\TimeModel;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use verbb\supertable\fields\SuperTableField;
use yii\base\ErrorException;

class ReadTimeTwigExtension extends AbstractExtension
{
    // Public Methods
    // =========================================================================

    public function getName(): string
	{
        return 'readTime';
    }

    public function getFunctions(): array
	{
        return [
            new TwigFunction('readTime', [$this, 'readTimeFunction']),
        ];
    }

    public function getFilters(): array
	{
        return [
            new TwigFilter('readTime', [$this, 'readTimeFilter']),
        ];
    }

	/**
	 * @throws InvalidFieldException
	 */
	public function readTimeFunction($element, $showSeconds = true): TimeModel
	{
        $totalSeconds = 0;

        if ($element instanceof Entry) {
            // Provided value is an entry

            foreach ($element->getFieldLayout()?->getCustomFields() as $field) {

                try {
                    // If field is a matrix then loop through fields in block
                    if ($field instanceof Matrix) {
                        foreach($element->getFieldValue($field->handle)->all() as $block) {
                            $blockFields = $block->getFieldLayout()->getCustomFields();

                            foreach ($blockFields as $blockField) {
                                $value = $block->getFieldValue($blockField->handle);
                                $seconds = $this->valToSeconds($value);
                                $totalSeconds += $seconds;
                            }
                        }
                    }
                    elseif ($field instanceof SuperTableField) {
                        foreach($element->getFieldValue($field->handle)->all() as $block) {
                            $blockFields = $block->getFieldLayout()->getCustomFields();

                            foreach ($blockFields as $blockField) {
                                if ($blockField instanceof Matrix) {
                                    foreach($block->getFieldValue($blockField->handle)->all() as $matrix) {
                                        $matrixFields = $matrix->getFieldLayout()->getCustomFields();

                                        foreach ($matrixFields as $matrixField) {
                                            $value = $matrix->getFieldValue($matrixField->handle);
                                            $seconds = $this->valToSeconds($value);
                                            $totalSeconds += $seconds;
                                        }
                                    }
                                } else {
                                    $value = $block->getFieldValue($blockField->handle);
                                    $seconds = $this->valToSeconds($value);
                                    $totalSeconds += $seconds;
                                }
                            }
                        }
                    }
                    else {
                        $value = $element->getFieldValue($field->handle);
                        $seconds = $this->valToSeconds($value);
                        $totalSeconds += $seconds;
                    }
                } catch (ErrorException $e) {
                    continue;
                }
            }
        }
        elseif (is_array($element)) {
            // Matrix field with one or more Entry elements
            Craft::info('matrix field provided', 'readtime');

            foreach ($element as $block) {
                if ($block instanceof Entry) {
                    $blockFields = $block->getFieldLayout()?->getCustomFields();

                    foreach ($blockFields as $blockField) {
                        $value = $block->getFieldValue($blockField->handle);
                        $seconds = $this->valToSeconds($value);
                        $totalSeconds += $seconds;
                    }
                }
            }
        }

        $data = [
            'seconds'     => $totalSeconds,
            'showSeconds' => $showSeconds,
        ];

        return new TimeModel($data);
    }

    public function readTimeFilter($value = null, $showSeconds = true): TimeModel
	{
        $seconds = $this->valToSeconds($value);

        $data = compact('seconds', 'showSeconds');

        return new TimeModel($data);
    }

    // Private Methods
    // =========================================================================

    private function valToSeconds($value): float
	{
		/** @var Settings $settings */
        $settings = ReadTime::getInstance()?->getSettings();
        $wpm = $settings->wordsPerMinute;

        $string = StringHelper::toString($value);
        $wordCount = StringHelper::countWords($string);

        return floor($wordCount / $wpm * 60);
    }
}
