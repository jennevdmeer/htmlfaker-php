<?php

namespace Impulze\HtmlFaker;

use Faker\Generator;
use RuntimeException;

class HtmlFaker
{
    private const TABLE_COLUMN_VALUE_TYPES = [
        'int',
        'currency',
        'percentage',
        'string',
        'text',
    ];

    public static array $defaultOptions = [
        'generator' => null,

        'paragraphs' => 3,
        'paragraphLength' => 6,

        'headings' => true,
        'headingLevel' => 1,
        'headingProbability' => 0.25,
        'links' => true,

        'blockElementProbability' => 0.25,
        'listLength' => 6,
        'unorderedLists' => false,
        'orderedLists' => false,
        'blockquotes' => false,
        'tables' => false,
        'tableRowLength' => 25,
        'pre' => false,

        'inlineElementProbability' => 0.25,
        'inlineElementProbabilities' => [
            'strong' => 1.0,
            'b' => 1.0,
            'em' => 1.0,
            'i' => 1.0,
            'mark' => 0.1,
            'abbr' => 0.1,
            'code' => 0.1,
        ],
    ];

    public readonly array $options;

    public function __construct(
        private readonly ?Generator $faker = null,
    ) {
    }

    private function faker(array $options): Generator
    {
        return $options['faker']
            ?? $this->faker
            ?? throw new RuntimeException('No faker instance provided in constructor or config.');
    }

    public function generate(?array $options = null): string
    {
        $options = array_merge(self::$defaultOptions, $options);

        $html = '';

        if ($this->options['headings'] && $this->options['headingLevel'] === 1) {
            $html .= $this->generateHeading($options);
        }

        $paragraphCount = (int)$this->options['paragraphs'];
        for ($i = 0; $i < $paragraphCount; $i++) {
            $options = [
                'paragraphCount' => $i,
                'paragraphProgress' => $i / $paragraphCount,
            ];

            // Generate heading before next block element/paragraph.
            if ($this->randomChance($this->options['headingProbability'])) {
                // 40% to go deeper, 60% to go back up
                if ($this->randomChance(.4)) {
                    ++$options['headingLevel'];

                    $html .= $this->generateHeading($options);
                } else {
                    $options['headingLevel'] = max(1, $options['headingLevel'] - 1);
                }
            }

            // Random chance to generate a block element before the paragraph.
            if ($this->randomChance($this->options['blockElementProbability'])) {
                $html .= $this->randomBlockElement($options);
            } else {
                $html .= $this->generateParagraph($options);
            }
        }

        return $html;
    }

    private function generateHeading(array $options = []): string
    {
        return sprintf('<h%1$d>%2$s</h%1$d>', $options['headingLevel'], $this->faker($options)->sentence());
    }

    private function generateParagraph(array $options = []): string
    {
        $paragraphLength = (int)$this->options['paragraphLength'];
        $paragraphLength += max(1, (int)($paragraphLength * (random_int(-20, 20) / 100)));

        $html = '';
        for ($i = 0; $i < $paragraphLength; $i++) {
            $html .= $this->generateSentence($options);
        }

        return '<p>'.trim($html).'</p>';
    }

    private function generateSentence(array $options = []): string
    {
        if ($this->randomChance($this->options['inlineElementProbability'])) {
            $randomElement = $this->faker($options)->randomElement($this->options['inlineElementProbabilities']);

            return sprintf('<%1$s>%2$s</%1$s>', $randomElement, $this->faker($options)->sentence());
        }

        return $this->faker($options)->sentence();
    }

    private function randomBlockElement(?array $options): string
    {
        $probabilities = $options['blockElementProbabilities'];
        if ($options['links']) {
            $probabilities['a'] = 0.1;
        }

        $element = $this->randomWeighted($probabilities);

        return match($element) {
            'ul' => $this->generateUnorderedList($options),
            'ol' => $this->generateOrderedList($options),
            'blockquote' => $this->generateBlockquote($options),
            'table' => $this->generateTable($options),
            'pre' => $this->generatePre($options),
            default => throw new RuntimeException('Unknown block element: '.$element),
        };
    }

    private function generateUnorderedList(array $options): string
    {
        return '<ul>'.$this->generateListItems($options).'</ul>';
    }

    private function generateOrderedList(array $options): string
    {
        return '<ol>'.$this->generateListItems($options).'</ol>';
    }

    private function generateListItems(array $options): string
    {
        $listLength = $this->randomVariation((int)$options['listLength']);

        $items = [];
        for ($i = 0, $max = max(1, $listLength); $i < $max; $i++) {
            $items[] = '<li>'.$this->faker($options)->sentence().'</li>';
        }

        return implode($items);
    }

    private function generateBlockquote(array $options): string
    {
        /** @var string[] $sentences */
        $sentences = $this->faker($options)->sentences(random_int(1, 3));
        $sentences = '<p>'.implode('</p><p>', $sentences).'</p>';

        return '<blockquote>'.$sentences.'</blockquote>';
    }

    private function generateTable(array $options): string
    {
        $columnCount = random_int(1, count(self::TABLE_COLUMN_VALUE_TYPES));

        $types = [];
        for ($i = 0; $i < $columnCount; $i++) {
            $randomKey = array_rand(self::TABLE_COLUMN_VALUE_TYPES);

            $types[] = self::TABLE_COLUMN_VALUE_TYPES[$randomKey];
        }

        return '<table>'.$this->generateTableHeader($options, $types).$this->generateTableRows($options, $types).'</table>';
    }

    private function generateTableHeader(array $columnTypes, array $options): string
    {
        $columns = '';
        foreach ($columnTypes as $type) {
            $columns .= '<th>'.$this->faker($options)->word().'</th>';
        }

        return '<thead><tr>'.$columns.'</tr></thead>';
    }

    private function generateTableRows(array $options, array $columnTypes): string
    {
        $rows = '';

        $rowLength = $this->randomVariation($options['tableRowLength']);

        for ($i = 0; $i < $rowLength; $i++) {
            $rows .= $this->generateTableRow($options, $columnTypes);
        }

        return '<tbody>'.$rows.'</tbody>';
    }

    private function generateTableRow(array $options, array $columnTypes): string
    {
        $columns = '';
        foreach ($columnTypes as $type) {
            $value = match ($type) {
                'int' => $this->faker($options)->randomNumber(),
                'currency' => '€ '.$this->faker($options)->randomFloat(2, 0, 100),
                'percentage' => $this->faker($options)->randomFloat(2, 0, 100).'%',
                'string' => $this->faker($options)->word(),
                'text' => $this->faker($options)->sentence(),
                default => throw new RuntimeException('Unknown table column type: '.$type),
            };

            $columns .= '<td>'.$value.'</td>';
        }

        return '<tr>'.$columns.'</tr>';
    }

    private function generatePre(array $options): string
    {
        return '<pre>'.$this->faker($options)->sentences(asText: true).'</pre>';
    }

    private function randomFloat(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    private function randomFloatBetween(float $min = 0, float $max = 1): float
    {
        return $min + $this->randomFloat() * ($max - $min);
    }

    private function randomChance(float $probability): bool
    {
        return $this->randomFloat() < $probability;
    }

    private function randomVariation(int|float $number, float $offsetPercentage = 0.4): int|float
    {
        $range = $number * $offsetPercentage;
        if (is_float($number)) {
            return $this->randomFloatBetween($number - $range, $number + $range);
        }

        return random_int(floor($number - $range), ceil($number + $range));
    }

    private function randomWeighted(array $items): string|int
    {
        if (empty($items)) {
            return throw new RuntimeException('Cannot do weighted select from empty array.');
        }

        if (count($items) === 1) {
            return array_key_first($items);
        }

        $total = array_sum($items);
        $rand = $this->randomFloat() * $total;

        foreach ($items as $key => $value) {
            $rand -= $value;
            if ($rand <= 0) {
                return $key;
            }
        }

        throw new RuntimeException('Hum why?');
    }
}
