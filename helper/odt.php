<?php
/**
 * ODT (Open Document format) export for Typography plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Lars (LarsDW223)
 *
 * Local fork: array() -> [] short syntax; list() -> [] destructuring.
 * See README.md.
 */

if (!defined('DOKU_INC')) die();

class helper_plugin_typography_odt extends DokuWiki_Plugin
{
    protected $closing_stack = null; // used in render()

    /**
     * Render ODT output for a typography span/paragraph
     *
     * @param Doku_Renderer $renderer ODT renderer instance
     * @param array         $data     data returned by the syntax handle() method
     * @return bool
     */
    public function render(Doku_Renderer $renderer, $data)
    {
        [$state, $tag_data] = $data;

        if (is_null($this->closing_stack)) {
            $this->closing_stack = new SplStack();
        }

        switch ($state) {
            case DOKU_LEXER_ENTER:
                // build inline css
                $css = [];
                foreach (($tag_data['declarations'] ?? []) as $name => $value) {
                    $css[] = $name.':'.$value.';';
                }
                $style = implode(' ', $css);

                if (isset($tag_data['declarations']['line-height'])) {
                    $renderer->p_close();
                    if (method_exists($renderer, '_odtParagraphOpenUseCSSStyle')) {
                        $renderer->_odtParagraphOpenUseCSSStyle($style);
                    } else {
                        $renderer->_odtParagraphOpenUseCSS('p', 'style="'.$style.'"');
                    }
                    $this->closing_stack->push('p');
                } else {
                    if (method_exists($renderer, '_odtSpanOpenUseCSSStyle')) {
                        $renderer->_odtSpanOpenUseCSSStyle($style);
                    } else {
                        $renderer->_odtSpanOpenUseCSS('span', 'style="'.$style.'"');
                    }
                    $this->closing_stack->push('span');
                }
                break;

            case DOKU_LEXER_EXIT:
                try {
                    $content = $this->closing_stack->pop();
                    if ($content == 'p') {
                        // For closing paragraphs use the renderer's function otherwise the internal
                        // counter in the ODT renderer is corrupted and so would be the ODT file.
                        $renderer->p_close();
                        $renderer->p_open();
                    } else {
                        // Close the span.
                        $renderer->_odtSpanClose();
                    }
                } catch (Exception $e) {
                    // Stack underflow etc. — intentionally swallowed.
                    // May be uncommented for debugging purposes:
                    //$renderer->doc .= $e->getMessage();
                }
                break;
        }
        return true;
    }
}
