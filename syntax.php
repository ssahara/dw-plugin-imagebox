<?php
/**
 * DokuWiki Plugin Imagebox
 *
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  Lukas Rademacher <lukas@rademacher.ac>, FFTiger & myst6re
 *
 * Syntax for display an image with a caption, like Wikipedia.org
 */
if(!defined('DOKU_INC')) die();

class syntax_plugin_imagebox extends DokuWiki_Syntax_Plugin {

    protected $mode;

    function __construct() {
        $this->mode = substr(get_class($this), 7);
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
        $this->Lexer->addEntryPattern('\[\{\{[^\|\}]+\|*(?=[^\}]*\}\}\])', $mode, $this->mode);
    }
    function postConnect() {
        $this->Lexer->addExitPattern('\}\}\]', $this->mode);
    }

    /**
     * handle syntax
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {
        switch($state){
            case DOKU_LEXER_ENTER:
                $m = Doku_Handler_Parse_Media(substr($match,3));

                // check whether the click-enlarge icon is shown
                $dispMagnify = ($m['width'] || $m['height'])
                             && ($this->getConf('display_magnify') == 'If necessary')
                             || ($this->getConf('display_magnify') == 'Always');

                list($src, $hash) = explode('#', $m['src'], 2);

                if ($m['type'] == 'internalmedia') {
                    global $ID;
                    $exists = false;
                    resolve_mediaid(getNS($ID), $src, $exists);

                    if ($dispMagnify) {
                        $m['detail'] = ml($src,array('id'=>$ID,'cache'=>$m['cache']),($m['linking']=='direct'));
                        if ($hash) {
                            $m['detail'] .= '#'.$hash;
                        }
                    }

                    $gimgs = $exists ? @getImageSize(mediaFN($src)) : false;
                } else {
                    if ($dispMagnify) {
                        $m['detail'] = ml($src, array('cache'=>'cache'), false);
                        if ($hash) {
                            $m['detail'] .= '#'.$hash;
                        }
                    }

                    $gimgs = @getImageSize($src);
                }

                $m['exist'] = ($gimgs !== false);

                // get image width $m['width'], which required to decide thumbinner width
                if (!$m['width'] && $m['exist']) {
                    ($m['height'])?
                    $m['width'] = round($m['height'] * $gimgs[0]/$gimgs[1]):
                    $m['width'] = $gimgs[0];
                }

                // imagebox alignment
                if (!$m['align'] || $m['align']=='center' && !$this->getConf('center_align')) {
                    $m['align'] = 'rien';
                }
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
                // picture image
                $renderer->doc.= '<div class="thumb2 t'.$m['align'].'">';
                if ($m['exist']) {
                    $renderer->doc.= '<div class="thumbinner" style="width: '.($m['width']+2).'px;">';
                    $renderer->{$m['type']}($m['src'],$m['title'],'box2',$m['width'],$m['height'],$m['cache'],$m['linking']);
                } else {
                    $renderer->doc.= '<div class="thumbinner">';
                    $renderer->doc.= '<div class="error">Invalid image</div>';
                }
                // image caption
                $renderer->doc.= '<div class="thumbcaption">';
                if ($m['detail']) {
                    $renderer->doc.= '<div class="magnify">';
                    $renderer->doc.= '<a class="internal" title="'.$this->getLang('enlarge').'" href="'.$m['detail'].'">';
                    $renderer->doc.= '<img width="15" height="11" alt="" src="'.DOKU_REL.'lib/plugins/imagebox/magnify-clip.png"/>';
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
