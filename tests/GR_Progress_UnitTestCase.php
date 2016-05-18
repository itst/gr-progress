<?php

namespace relativisticramblings\gr_progress;

require_once('HTML5Validate.php');

class GR_Progress_UnitTestCase extends \WP_UnitTestCase {

    private $DEFAULT_SETTINGS = [
        'title' => 'Currently reading',
        'goodreadsAttribution' => 'Data from Goodreads',
        'userid' => '55769144',
        'apiKey' => 'HAZB53duIFj5Ur87DiOW7Q',
        'currentlyReadingShelfName' => 'currently-reading',
        'emptyMessage' => 'Not currently reading anything.',
        'displayReviewExcerptCurrentlyReadingShelf' => false,
        'sortByReadingProgress' => false,
        'displayProgressUpdateTime' => true,
        'intervalTemplate' => '{num} {period} ago',
        'intervalSingular' => ['year', 'month', 'week', 'day', 'hour', 'minute', 'second'],
        'intervalPlural' => ['years', 'months', 'weeks', 'days', 'hours', 'minutes', 'seconds'],
        'useProgressBar' => true,
        'currentlyReadingShelfSortBy' => 'date_updated',
        'currentlyReadingShelfSortOrder' => 'd',
        'maxBooksCurrentlyReadingShelf' => 15,
        'additionalShelfName' => 'to-read',
        'additionalShelfHeading' => 'Reading soon',
        'emptyMessageAdditional' => "Nothing planned at the moment.",
        'displayReviewExcerptAdditionalShelf' => false,
        'additionalShelfSortBy' => 'position',
        'additionalShelfSortOrder' => 'a',
        'maxBooksAdditionalShelf' => 15,
        'progressCacheHours' => 24,
        'bookCacheHours' => 24,
        'regenerateCacheOnSave' => false,
        'deleteCoverURLCacheOnSave' => false,
    ];
    private $DEFAULT_ARGS = [
        'before_widget' => '',
        'after_widget' => '',
        'before_title' => '',
        'after_title' => '',
    ];

    /**
     * Returns the HTML used for rendering the widget.
     * @param array $overrideSettings Key-value pairs of settings to override
     * @return string
     */
    public function getWidgetHTML($overrideSettings = []) {
        $settings = $this->getSettingsWithOverride($overrideSettings);

        $widget = new gr_progress_cvdm_widget();
        
        ob_start();
        $widget->widget($this->DEFAULT_ARGS, $settings);
        $html = ob_get_clean();
        return $html;
    }

    private function getSettingsWithOverride($overrideSettings) {
        $settings = $this->DEFAULT_SETTINGS;
        foreach ($overrideSettings as $k => $v) {
            $this->assertArrayHasKey($k, $settings, "Attempted to override non-existent setting $k");
            $settings[$k] = $v;
        }
        return $settings;
    }

    /**
     * Returns the HTML used for rendering the widget settings form.
     * @return string
     */
    public function getWidgetForm() {
        $widget = new gr_progress_cvdm_widget();

        ob_start();
        $widget->form($this->DEFAULT_SETTINGS);
        $html = ob_get_clean();
        return $html;
    }

    public function getNewSettings($overrideSettings = []) {
        $settings = $this->getSettingsWithOverride($overrideSettings);

        $widget = new gr_progress_cvdm_widget();

        $newSettings = $widget->update($settings, $this->DEFAULT_SETTINGS);
        return $newSettings;
    }

    public function assertSettingSavedAs($setting, $inputValue, $expectedOutput) {
        $settings = $this->getNewSettings([$setting => $inputValue]);
        $this->assertEquals($expectedOutput, $settings[$setting]);
    }

    public function assertSettingTextIsSaved($setting) {
        $this->assertSettingSavedAs($setting, "foobar", "foobar");
    }

    public function assertSettingHTMLEscaped($setting) {
        $this->assertSettingSavedAs($setting, "<script>evil()</script>foobar", "&lt;script&gt;evil()&lt;/script&gt;foobar");
    }

    public function assertSettingWhitespaceRemoved($setting) {
        $this->assertSettingSavedAs($setting, " foobar ", "foobar");
    }

    public function assertPassesAllTextInputTests($setting) {
        $this->assertSettingTextIsSaved($setting);
        $this->assertSettingHTMLEscaped($setting);
        $this->assertSettingWhitespaceRemoved($setting);
    }

    public function assertCheckedSettingCorrectlyUpdated($setting) {
        $this->assertArrayHasKey($setting, $this->DEFAULT_SETTINGS, "Attempted to test nonexistent setting $setting");

        $widget = new gr_progress_cvdm_widget();

        $settingsWithCheckedOption = $this->DEFAULT_SETTINGS;
        $parsedSettingsChecked = $widget->update($settingsWithCheckedOption, $this->DEFAULT_SETTINGS);
        $this->assertTrue($parsedSettingsChecked[$setting]);

        $settingsWithoutCheckedOption = array_diff_key($this->DEFAULT_SETTINGS, [$setting => '']);
        $parsedSettingsUnchecked = $widget->update($settingsWithoutCheckedOption, $this->DEFAULT_SETTINGS);
        $this->assertFalse($parsedSettingsUnchecked[$setting]);
    }

    /**
     * Asserts that input string is valid HTML.
     * @param string $html
     */
    public function assertIsValidHTML($html) {
        $validator = new \HTML5Validate();
        $result = $validator->Assert($html);
        $this->assertTrue($result, $validator->message);
    }

    /**
     * Asserts that the book titles on the primary shelf contains the substrings
     * given in $bookTitles, in order.
     * @param string[] $bookTitles
     * @param string $html
     */
    public function assertOrderedBookTitlesOnPrimaryShelfContains($bookTitles, $html) {
        $dom = str_get_html($html);
        $primaryShelf = $dom->find('.currently-reading-shelf', 0);
        $this->assertOrderedBookTitlesContains($bookTitles, $primaryShelf);
    }

    /**
     * Asserts that the book titles on the secondary shelf contains the
     * substrings given in $bookTitles, in order.
     * @param string[] $bookTitles
     * @param string $html
     */
    public function assertOrderedBookTitlesOnSecondaryShelfContains($bookTitles, $html) {
        $dom = str_get_html($html);
        $secondaryShelf = $dom->find('.additional-shelf', 0);
        $this->assertOrderedBookTitlesContains($bookTitles, $secondaryShelf);
    }

    /**
     * Asserts that the book titles in the given dom element contains the
     * substrings given in $bookTitlesExpected, in order.
     * @param string[] $bookTitlesExpected
     * @param simple_html_dom_node $domElement
     */
    private function assertOrderedBookTitlesContains($bookTitlesExpected, $domElement) {
        $bookTitlesActual = [];
        foreach ($domElement->find(".bookTitle") as $bookTitleElement) {
            $bookTitlesActual[] = $bookTitleElement->plaintext;
        }

        $this->assertCount(count($bookTitlesExpected), $bookTitlesActual, "Shelf does not contain expected number of books");

        for ($i = 0; $i < count($bookTitlesExpected); $i++) {
            $this->assertContains($bookTitlesExpected[$i], $bookTitlesActual[$i], "Wrong book on index " . $i);
        }
    }

    /**
     * Asserts that there is no primary shelf in the input string.
     * @param string $html
     */
    public function assertNoPrimaryShelf($html) {
        $dom = str_get_html($html);
        $this->assertEmpty($dom->find('.currently-reading-shelf'), "Found primary shelf but expected none");
    }

    /**
     * Asserts that there is no secondary shelf in the input string.
     * @param string $html
     */
    public function assertNoSecondaryShelf($html) {
        $dom = str_get_html($html);
        $this->assertEmpty($dom->find('.additional-shelf'), "Found secondary shelf but expected none");
    }

    /**
     * Asserts that the primary shelf contains the books for the default settings.
     * @param string $html
     */
    public function assertDefaultBooksOnPrimaryShelf($html) {
        $this->assertOrderedBookTitlesOnPrimaryShelfContains([
            "A Game of Thrones",
            "The Chronicles of Narnia",
            "The Lord of the Rings",
            "Harry Potter and the Sorcerer"], $html);
    }

    /**
     * Asserts that the secondary shelf contains the books for the default settings.
     * @param string $html
     */
    public function assertDefaultBooksOnSecondaryShelf($html) {
        $this->assertOrderedBookTitlesOnSecondaryShelfContains([
            "The Name of the Wind",
            "The Eye of the World",
            "His Dark Materials",
            "The Lightning Thief",
            "Mistborn",
            "City of Bones",
            "The Way of Kings",
            "The Gunslinger",
            "The Color of Magic",
            "Artemis Fowl"], $html);
    }

    /**
     * Asserts that the book with name mathing $bookName contains a comment
     * matching $commentString.
     * @param string $bookNameSubstring
     * @param string $commentSubstring
     * @param string $html
     */
    public function assertBookHasComment($bookNameSubstring, $commentSubstring, $html) {
        $this->assertBookDescriptionFieldContains($bookNameSubstring, ".bookComment", $commentSubstring, $html);
    }

    public function assertBookHasNoComment($bookNameSubstring, $html) {
        $this->assertBookDoesNotHaveDescriptionField($bookNameSubstring, ".bookComment", $html);
    }

    public function assertBookProgressContains($bookNameSubstring, $progressInPercent, $html) {
        $this->assertBookDescriptionFieldContains($bookNameSubstring, ".progress", $progressInPercent, $html);
    }

    public function assertBookProgressNotContains($bookNameSubstring, $progressInPercent, $html) {
        $this->assertBookDescriptionFieldNotContains($bookNameSubstring, ".progress", $progressInPercent, $html);
    }

    public function assertBookHasNoProgress($bookNameSubstring, $html) {
        $this->assertBookDoesNotHaveDescriptionField($bookNameSubstring, ".progress", $html);
    }

    private function assertBookDescriptionFieldContains($bookNameSubstring, $descriptionFieldSelector, $fieldContains, $html) {
        $this->assertBookDescrptionFieldContainsOrNot($bookNameSubstring, $descriptionFieldSelector, $fieldContains, false, $html);
    }

    private function assertBookDescriptionFieldNotContains($bookNameSubstring, $descriptionFieldSelector, $fieldContains, $html) {
        $this->assertBookDescrptionFieldContainsOrNot($bookNameSubstring, $descriptionFieldSelector, $fieldContains, true, $html);
    }

    private function assertBookDescrptionFieldContainsOrNot($bookNameSubstring, $descriptionFieldSelector, $fieldContains, $exclude, $html) {
        $this->assertBookExists($bookNameSubstring, $html);
        $dom = str_get_html($html);
        foreach ($dom->find('.desc') as $descriptionElement) {
            $bookTitle = $descriptionElement->find(".bookTitle", 0)->plaintext;
            $titleMatches = strpos($bookTitle, $bookNameSubstring) !== false;
            if ($titleMatches) {
                $descriptionFieldElement = $descriptionElement->find($descriptionFieldSelector);
                $this->assertNotEmpty($descriptionFieldElement,
                        "Expected element with selector '$descriptionFieldSelector' but found none for book $bookNameSubstring");
                if ($exclude) {
                    $this->assertNotContains($fieldContains, $descriptionFieldElement[0]->innertext,
                            "Element with selector '$descriptionFieldSelector' contains substring for book " . $bookNameSubstring);
                } else {
                    $this->assertContains($fieldContains, $descriptionFieldElement[0]->innertext,
                            "Element with selector '$descriptionFieldSelector' does not contain expected substring for book " . $bookNameSubstring);
                }
                break;
            }
        }
    }

    public function assertBookDoesNotHaveDescriptionField($bookNameSubstring, $descriptionFieldSelector, $html) {
        $this->assertBookExists($bookNameSubstring, $html);
        $dom = str_get_html($html);
        foreach ($dom->find('.desc') as $descriptionElement) {
            $bookTitle = $descriptionElement->find(".bookTitle", 0)->plaintext;
            $titleMatches = strpos($bookTitle, $bookNameSubstring) !== false;
            if ($titleMatches) {
                $descriptionFieldElements = $descriptionElement->find($descriptionFieldSelector);
                $this->assertEmpty($descriptionFieldElements,
                        "Found unexpected element with selector '$descriptionFieldSelector' for book $bookNameSubstring");
                break;
            }
        }
    }

    public function assertNoBooksHaveComment($html) {
        $dom = str_get_html($html);
        foreach ($dom->find('.desc') as $descriptionElement) {
            $bookTitle = $descriptionElement->find(".bookTitle", 0)->plaintext;
            $bookCommentElements = $descriptionElement->find(".bookComment");
            $this->assertEmpty($bookCommentElements, "Expected no comment but found comment for book " . $bookTitle);
        }
    }

    public function assertBookExists($bookNameSubstring, $html) {
        $ok = false;
        $dom = str_get_html($html);
        foreach ($dom->find('.bookTitle') as $bookTitleElement) {
            $bookTitle = $bookTitleElement->plaintext;
            $titleMatches = strpos($bookTitle, $bookNameSubstring) !== false;
            if ($titleMatches) {
                $ok = true;
                break;
            }
        }
        $this->assertTrue($ok, "No books found with title mathcing " . $bookNameSubstring);
    }

    public function assertAllBooksHaveCoverImage($html) {
        $dom = str_get_html($html);
        $books = $dom->find(".book");
        $this->assertNotEmpty($books, "Shelves empty, cannot check for cover images");
        foreach ($books as $book) {
            $img = $book->find("img", 0);
            $bookTitle = $book->find(".bookTitle", 0)->plaintext;
            $this->assertNotEmpty($img->src, "Missing cover on book $bookTitle");
        }
    }

}
