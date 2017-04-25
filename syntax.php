<?php
/**
 * DokuWiki Plugin Imagebox
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
 *     [200px{{wiki:dokuwiki-128.png|alternate text|caption or description}}]
 */
if(!defined('DOKU_INC')) die();

class syntax_plugin_imagebox extends DokuWiki_Syntax_Plugin {

    protected $mode;
    protected $pattern;

    function __construct() {
        $this->mode = substr(get_class($this), 7);

        // match patterns
        $this->pattern['entry'] = '\[(?:\d+(?:%|px|em))?'
                                 .'\{\{[^\|\}]+(?:(?:\|[^\|\[\]\{\}]*?)?\|)?'
                                 .'(?=[^\}]*\}\}\])';
        $this->pattern['exit']  = '\}\}\]';
    }

    function getType(){ return 'protected'; }
    function getAllowedTypes() {
        return array('substition','protected','disabled','formatting');
    }
    function getSort(){ return 315; }
    function getPType(){ return 'block'; }

    /**
     * Connect pattern to lexer
     */
    function connectTo($mode) {
        $this->Lexer->addEntryPattern($this->pattern['entry'], $mode, $this->mode);
    }
    function postConnect() {
        $this->Lexer->addExitPattern($this->pattern['exit'], $this->mode);
    }

    /**
     * handle syntax
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {
        switch($state){
            case DOKU_LEXER_ENTER:
                list($size, $media) = explode('{{', $match, 2);

                $m = Doku_Handler_Parse_Media(rtrim($media,'|'));

                $m['size'] = substr($size, 1); // imagebox width with unit

                list($src, $hash) = explode('#', $m['src'], 2);

                if ($m['type'] == 'internalmedia') {
                    global $ID;
                    $exists = false;
                    resolve_mediaid(getNS($ID), $src, $exists);
                    $m['detail'] = ml($src,array('id'=>$ID,'cache'=>$m['cache']),($m['linking']=='direct'));
                    $gimgs = $exists ? @getImageSize(mediaFN($src)) : false;
                } else {
                    $m['detail'] = ml($src, array('cache'=>'cache'), false);
                    $gimgs = @getImageSize($src);
                }

                if ($hash) {
                    $m['detail'] .= '#'.$hash;
                }

                $m['exist'] = ($gimgs !== false);

                // get image width $m['width'], which required to decide thumbinner width
                if (!$m['width'] && $m['exist']) {
                    ($m['height'])?
                    $m['width'] = round($m['height'] * $gimgs[0]/$gimgs[1]):
                    $m['width'] = $gimgs[0];
                }

                // check whether the click-enlarge icon is shown
                switch ($this->getConf('display_magnify')) {
                    case 'Always':
                        $dispMagnify = true;
                        break;
                    case 'Never':
                        $dispMagnify = false;
                        break;
                    case 'If necessary':
                    default:
                        // chcek linking option (linkonly|detail|nolink|direct)
                        if (in_array($m['linking'],['nolink','linkonly','direct'])) {
                            $dispMagnify = false;
                        } else {
                            // check image size is greater than display size
                            $dispMagnify = ($gimgs[0] > $m['width']);
                        }
                }
                if (!$dispMagnify) $m['detail'] = false;

                // imagebox alignment, requires relevant class of wrap plugin
                if (!$m['align']) $m['align'] = 'noalign'; // wrap_noalign

                return array($state, $m);

            case DOKU_LEXER_UNMATCHED:
                return array($state, $match);

            case DOKU_LEXER_EXIT:
                return array($state, '');
        }
    }

    /**
     * Create output
     */
    function render($format, Doku_Renderer $renderer, $data) {

        if ($format !== 'xhtml') return false;

        list($state, $m) = $data;

        switch ($state) {
            case DOKU_LEXER_ENTER:
                // imagebox width adjustment
                if ($m['size']) {
                    $width = $m['size'];
                } elseif ($m['width']) {
                    $width = ($m['width']+(1+3+1+2)*2).'px';
                } else {
                    $width = 'auto';
                }
                $renderer->doc.= '<div class="plugin_imagebox plugin_wrap wrap_'.$m['align']
                                .'" style="width: '.$width.';">';
                $renderer->doc.= '<div class="thumbinner">';

                // picture image
                if ($m['exist']) {
                    $renderer->{$m['type']}($m['src'],$m['title'],'box2',$m['width'],$m['height'],$m['cache'],$m['linking']);
                } else {
                    $renderer->doc.= '<div class="error">Invalid image</div>';
                }
                // image caption
                $renderer->doc.= '<div class="thumbcaption">';
                if ($m['detail']) {
                    $renderer->doc.= '<div class="magnify">';
                    $renderer->doc.= '<a class="internal" title="'.$this->getLang('enlarge').'" href="'.$m['detail'].'">';
                    $renderer->doc.= '</a></div>';
                }
                break;

            case DOKU_LEXER_UNMATCHED:
                $match = $m;
                switch ($this->getConf('default_caption_style')) {
                    case 'Italic':
                        $renderer->doc.= '<em>'.$renderer->_xmlEntities($match).'</em>';
                        break;
                    case 'Bold':
                        $renderer->doc.= '<strong>'.$renderer->_xmlEntities($match).'</strong>';
                        break;
                    default:
                        $renderer->doc.= $renderer->_xmlEntities($match);
                }
                break;

            case DOKU_LEXER_EXIT:
                $renderer->doc.= '</div></div></div>';
                break;
        }
        return true;
    }
}
