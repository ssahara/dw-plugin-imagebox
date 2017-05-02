<?php
/**
 * DokuWiki Plugin Imagebox v2 - inline-block box mode
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  FFTiger <fftiger@wikisquare.com>
 * @author  myst6re <myst6re@wikiaquare.com>
 * @author  Lukas Rademacher <lukas@rademacher.ac>
 * @author  Satoshi Sahara <sahara.satoshi@gmail.com>
 *
 * display an image with a caption, like Wikipedia.org
 * note: require wrap plugin to support imagebox alignment
 *
 * Example:
 *     {200px imaegbox1{{wiki:dokuwiki-128.png|
 *     alternate text|caption or description
 *     }}}
 */
require_once(dirname(__FILE__).'/block.php');

class syntax_plugin_imagebox2_inline extends syntax_plugin_imagebox2_block {

    protected $mode;
    protected $pattern;
    protected $tag = 'span'; // used in render()

    function __construct() {
        $this->mode = substr(get_class($this), 7);

        // match patterns
        $this->pattern['entry'] = '\{(?:[\w ]+)?'
                                 .'\{\{[^\|\}]+(?:(?:\|[^\|\[\]\{\}]*?)?\|)?'
                                 .'(?=[^\}]*\}\}\})';
        $this->pattern['exit']  = '\}\}\}';
    }

    function getType(){ return 'protected'; }

    function getAllowedTypes() {
        return array('substition','protected','disabled','formatting');
    }

    function getSort(){ return 315; }
    function getPType(){ return 'normal'; }

}
