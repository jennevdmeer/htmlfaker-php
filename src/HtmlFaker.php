<?php

namespace Impulze\HtmlFaker;

use Faker\Generator;
use RuntimeException;

class HtmlFaker
{
    public static array $defaultOptions = [
        'generator' => null,

        'paragraphs' => 6,
        'paragraphsVariation' => 0.4,
        'paragraphLength' => 6,
        'paragraphLengthVariation' => 0.7,

        'headingLevel' => 1,
        'headingLevelMax' => 6,
        'headingProbability' => 0.2,

        'blockElementProbability' => 0.4,
        'blockElementProbabilities' => [
            'ul' => 1.0,
            'ol' => 0.75,
            'blockquote' => 0.4,
            'table' => 0.1,
            'figure' => 0.5,
            'img' => 0.25,
            'pre' => 0.1,
        ],

        'listLength' => 6,
        'listLengthVariation' => 0.3,

        'tableColumnLength' => 4,
        'tableColumnLengthVariation' => 0.5,
        'tableRowLength' => 12,
        'tableRowLengthVariation' => 0.7,

        'imageRatios' => [ 9/16, 3/4, 1 ],
        'imageSizeMin' => 480,
        'imageSizeMax' => 1920,
        'imageSizeVariation' => 0.3,

        'inlineElementProbability' => 0.2,
        'inlineElementProbabilities' => [
            'a' => 0.5,
            'strong' => 1.0,
            'em' => 1.0,
            'mark' => 0.1,
            'abbr' => 0.1,
            'code' => 0.1,
        ],

        'elementClasses' => [
            'figure' => 'figure',
            'table' => 'table',
            'blockquote' => 'blockquote',
        ],
    ];

    private const TABLE_COLUMN_VALUE_TYPES = [
        'int',
        'currency',
        'percentage',
        'string',
        'text',
    ];

    public function __construct(
        private readonly ?Generator $faker = null,
    ) {
    }

    public function generate(array $options = []): string
    {
        $options = array_merge(self::$defaultOptions, $options);

        $html = '';

        if ($options['headingProbability'] && $options['headingLevel'] === 1) {
            $html .= $this->heading($options);
            $options['headingLevel']++;
        }

        // Store original heading, so we don't end up with a lower heading level than our input.
        $options['originalHeadingLevel'] = $options['headingLevel'];

        $html .= $this->paragraphs($options);

        return $html;
    }

    public function paragraphs(array $options): string
    {
        $html = '';

        $paragraphCount = $this->randomVariation((int)$options['paragraphs'], $options['paragraphsVariation']);
        for ($i = 0; $i < $paragraphCount; $i++) {
            // Generate heading before next block element/paragraph.
            if ($options['headingProbability'] && $this->randomChance($options['headingProbability'])) {
                $html .= $this->heading($options);

                // 40% to go deeper, 60% to go back up per heading chance.
                if ($this->randomChance(.4)) {
                    ++$options['headingLevel'];
                } else {
                    $options['headingLevel'] = $this->clamp(
                        $options['headingLevel'] - 1,
                        $options['originalHeadingLevel'],
                        $options['headingLevelMax'],
                    );
                }
            }

            // Random chance to generate a block element otherwise just generate a normal paragraph.
            if ($this->randomChance($options['blockElementProbability'])) {
                $html .= $this->blockElement($options);
            } else {
                $html .= $this->paragraph($options);
            }
        }

        return $html;
    }

    public function heading(array $options = []): string
    {
        return sprintf('<h%1$d class="%2$s">%3$s</h%1$d>'.PHP_EOL, $options['headingLevel'], $options['elementClasses']['h'.$options['headingLevel']] ?? '', $this->faker($options)->sentence());
    }

    public function paragraph(array $options = []): string
    {
        $paragraphLength = max(1, $this->randomVariation((int)$options['paragraphLength'], $options['paragraphLengthVariation']));

        $html = '';
        for ($i = 0; $i < $paragraphLength; $i++) {
            $html .= $this->sentence($options).' ';
        }

        return '<p class="'.($options['elementClasses']['p'] ?? '').'">'.trim($html).'</p>'.PHP_EOL;
    }

    public function sentence(array $options = []): string
    {
        if ($this->randomChance($options['inlineElementProbability'])) {
            return $this->inlineElement($options);
        }

        return $this->faker($options)->sentence();
    }

    public function inlineElement(array $options): string
    {
        $element = $this->randomWeighted($options['inlineElementProbabilities']);

        return match ($element) {
            'a' => $this->link($options),
            'strong', 'b', 'em', 'i', 'mark', 'abbr', 'code' => sprintf(
                '<%1$s class="%2$s">%3$s</%1$s>',
                $element,
                $options['elementClasses'][$element] ?? '',
                $this->faker($options)->sentence(),
            ),
            default => throw new RuntimeException('Unknown inline element: '.$element),
        };
    }

    public function blockElement(array $options): string
    {
        $element = $this->randomWeighted($options['blockElementProbabilities']);

        return match($element) {
            'ul' => $this->unorderedList($options),
            'ol' => $this->orderedList($options),
            'blockquote' => $this->blockquote($options),
            'table' => $this->table($options),
            'img' => $this->image($options),
            'figure' => $this->figure($options),
            'pre' => $this->pre($options),
            default => throw new RuntimeException('Unknown block element: '.$element),
        };
    }

    public function link(array $options): string
    {
        return sprintf(
            '<a href="#%s">%s</a>',
            $this->faker($options)->url(),
            $this->faker($options)->sentence(),
        );
    }

    public function unorderedList(array $options): string
    {
        return '<ul class="'.($options['elementClasses']['ul'] ?? '').'">'.PHP_EOL.$this->listItems($options).'</ul>'.PHP_EOL;
    }

    public function orderedList(array $options): string
    {
        return '<ol class="'.($options['elementClasses']['ol'] ?? '').'">'.PHP_EOL.$this->listItems($options).'</ol>'.PHP_EOL;
    }

    private function listItems(array $options): string
    {
        $listLength = max(1, $this->randomVariation((int)$options['listLength'], $options['listLengthVariation']));

        $items = [];
        for ($i = 0, $max = max(1, $listLength); $i < $max; $i++) {
            $items[] = '<li class="'.($options['elementClasses']['li'] ?? '').'">'.$this->sentence($options).'</li>'.PHP_EOL;
        }

        return implode($items);
    }

    public function blockquote(array $options): string
    {
        /** @var string[] $sentences */
        $sentences = $this->faker($options)->sentences(random_int(1, 3));
        $sentences = '<p>'.implode('</p><p>', $sentences).'</p>';

        return '<blockquote class="'.($options['elementClasses']['blockquote'] ?? '').'">'.$sentences.'</blockquote>';
    }

    public function table(array $options): string
    {
        $columnCount = max(1, $this->randomVariation((int)$options['tableColumnLength'], $options['tableColumnLengthVariation']));

        $columnTypes = [];
        for ($i = 0; $i < $columnCount; $i++) {
            $randomKey = array_rand(self::TABLE_COLUMN_VALUE_TYPES);

            $columnTypes[] = self::TABLE_COLUMN_VALUE_TYPES[$randomKey];
        }

        return '<table class="'.($options['elementClasses']['table'] ?? '').'">'.PHP_EOL.$this->generateTableHeader($options, $columnTypes).$this->generateTableRows($options, $columnTypes).'</table>'.PHP_EOL;
    }

    private function generateTableHeader(array $options, array $columnTypes): string
    {
        $columns = '';
        foreach ($columnTypes as $type) {
            $columns .= '<th class="'.$type.'">'.$this->faker($options)->word().'</th>'.PHP_EOL;
        }

        return '<thead>'.PHP_EOL.'<tr>'.PHP_EOL.$columns.'</tr>'.PHP_EOL.'</thead>'.PHP_EOL;
    }

    private function generateTableRows(array $options, array $columnTypes): string
    {
        $rowLength = max(1, $this->randomVariation((int)$options['tableRowLength'], $options['tableRowLengthVariation']));

        $rows = '';
        for ($i = 0; $i < $rowLength; $i++) {
            $rows .= $this->generateTableRow($options, $columnTypes);
        }

        return '<tbody>'.PHP_EOL.$rows.'</tbody>'.PHP_EOL;
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

            $columns .= '<td>'.$value.'</td>'.PHP_EOL;
        }

        return '<tr>'.PHP_EOL.$columns.'</tr>'.PHP_EOL;
    }

    public function pre(array $options): string
    {
        return '<pre class="'.($options['elementClasses']['pre'] ?? '').'">'.implode(PHP_EOL, $this->faker($options)->sentences()).'</pre>';
    }

    public function figure(array $options): string
    {
        $caption = '';
        if ($this->randomChance()) {
            $caption = sprintf(
                '<figcaption class="%s">%s</figcaption>',
                $options['elementClasses']['figcaption'] ?? '',
                $this->faker($options)->sentence(),
            );
        }

        return sprintf(
            "<figure class=\"%s\">\n%s%s</figure>\n",
            $options['elementClasses']['figure'] ?? '',
            $this->image($options),
            $caption
        );
    }

    public function image(array $options): string
    {
        $ratio = $options['imageRatios'][array_rand($options['imageRatios'])] ?? 9 / 16;

        $imageSize = $this->randomFloatBetween($options['imageSizeMin'] ?? 640, $options['imageSizeMax'] ?? 1920);

        $width = $this->randomVariation((int)$imageSize, $options['imageSizeVariation']);
        $height = $width * $ratio;

        return sprintf(
            '<img src="https://picsum.photos/%d/%d" title="%s" class="%s">'.PHP_EOL,
            $width,
            $height,
            $this->faker($options)->sentence(),
            $options['elementClasses']['img'] ?? ''
        );
    }

    private function faker(array $options): Generator
    {
        return $options['faker']
            ?? $this->faker
            ?? throw new RuntimeException('No faker instance provided in constructor or config.');
    }

    private function randomFloat(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    private function randomFloatBetween(float $min = 0, float $max = 1): float
    {
        return $min + $this->randomFloat() * ($max - $min);
    }

    private function randomChance(float $probability = 0.5): bool
    {
        return $this->randomFloat() < $probability;
    }

    private function randomVariation(int|float $number, float $offsetPercentage = 0.7): int|float
    {
        $range = $number * $offsetPercentage;
        if (is_float($number)) {
            return $this->randomFloatBetween($number - $range, $number + $range);
        }

        return random_int(floor($number - $range), ceil($number + $range));
    }

    /**
     * @return array-key
     */
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

    private function clamp(int $value, int $min, int $max): int
    {
        return max($min, min($max, $value));
    }
}
