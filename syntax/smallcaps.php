<?php
/**
 * DokuWiki Plugin Typography; Syntax typography smallcaps
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * Local fork: get_class($this) -> static::class; dirname(__FILE__) -> __DIR__;
 * array() -> [] short syntax. See README.md.
 */

require_once(__DIR__.'/base.php');

class syntax_plugin_typography_smallcaps extends syntax_plugin_typography_base
{
    /**
     * Connect pattern to lexer
     */
    public function preConnect()
    {
        // drop 'syntax_' from class name
        $this->mode = substr(static::class, 7);

        // syntax pattern
        $this->pattern[1] = '<smallcaps\b.*?>(?=.*?</smallcaps>)';
        $this->pattern[4] = '</smallcaps>';
    }

    /*
     * Handle the match
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        switch($state) {
            case DOKU_LEXER_ENTER:
                // load prameter parser utility
                if (is_null($this->styler)) {
                    $this->styler = $this->loadHelper('typography_parser');
                }

                // get inline CSS parameter (smallcaps always sets fv:small-caps)
                $params = 'fv:small-caps;'.strtolower(ltrim(substr($match, 10, -1)));

                // get css property:value pairs as an associative array
                $tag_data = $this->styler->parse_inlineCSS($params);

                return $data = [$state, $tag_data];

            case DOKU_LEXER_UNMATCHED:
                $handler->base($match, $state, $pos);
                return false;

            case DOKU_LEXER_EXIT:
                return $data = [$state, ''];
        }
        return [];
    }

}
