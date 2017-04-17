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
                $match=Doku_Handler_Parse_Media(substr($match,3));
                $match['w'] = $match['width'];
                $dispMagnify = ($match['w'] || $match['height']) && $this->getConf('display_magnify')=='If necessary' || $this->getConf('display_magnify')=='Always';

                $gimgs = false;
                list($src,$hash) = explode('#',$match['src'],2);

                if ($match['type'] == 'internalmedia') {
                    global $ID;
                    $exists = false;
                    resolve_mediaid(getNS($ID), $src, $exists);

                    if ($dispMagnify) {
                        $match['detail'] = ml($src,array('id'=>$ID,'cache'=>$match['cache']),($match['linking']=='direct'));
                        if ($hash) {
                            $match['detail'] .= '#'.$hash;
                        }
                    }

                    if ($exists) $gimgs = @getImageSize(mediaFN($src));
                } else {
                    if ($dispMagnify) {
                        $match['detail'] = ml($src,array('cache'=>'cache'),false);
                        if ($hash) {
                            $match['detail'] .= '#'.$hash;
                        }
                    }

                    $gimgs = @getImageSize($src);
                }

                $match['exist'] = $gimgs!==false;

                if (!$match['w'] && $match['exist']) {
                    ($match['height'])?
                    $match['w'] = $match['height']*$gimgs[0]/$gimgs[1]:
                    $match['w'] = $gimgs[0];
                }
                $match['width'] = $match['w'];
                $match['height'] = $match['h'];
                if (!$match['align'] || $match['align']=='center'&&!$this->getConf('center_align')) {
                    $match['align'] = 'rien';
                }
                return array($state,$match);

            case DOKU_LEXER_UNMATCHED:
                return array($state,$match);

            case DOKU_LEXER_EXIT:
                return array($state,$match);
        }
    }

    /**
     * Create output
     */
    function render($format, Doku_Renderer $renderer, $data) {
        if ($format == 'xhtml') {
            list($state,$match) = $data;

            switch ($state) {
                case DOKU_LEXER_ENTER:
                    $renderer->doc.= '<div class="thumb2 t'.$match['align'].'">';
                    $renderer->doc.= '<div class="thumbinner">';
                    if ($match['exist']) {
                        $renderer->{$match['type']}($match['src'],$match['title'],'box2',$match['width'],$match['height'],$match['cache'],$match['linking']);
                    } else {
                        $renderer->doc.= 'Invalid Link';
                    }
                    $renderer->doc.= '<div class="thumbcaption" style="max-width: '.($match['width']-6).'px">';
                    if ($match['detail']) {
                        $renderer->doc.= '<div class="magnify">';
                        $renderer->doc.= '<a class="internal" title="'.$this->getLang('enlarge').'" href="'.$match['detail'].'">';
                        $renderer->doc.= '<img width="15" height="11" alt="" src="'.DOKU_BASE.'lib/plugins/imagebox/magnify-clip.png"/>';
                        $renderer->doc.= '</a></div>';
                    }
                    break;

                case DOKU_LEXER_UNMATCHED:
                    $style=$this->getConf('default_caption_style');
                    if ($style=='Italic') {
                        $renderer->doc .= '<em>'.$renderer->_xmlEntities($match).'</em>';
                    } elseif ($style=='Bold') {
                        $renderer->doc .= '<strong>'.$renderer->_xmlEntities($match).'</strong>';
                    } else {
                        $renderer->doc .= $renderer->_xmlEntities($match);
                    }
                    break;

                case DOKU_LEXER_EXIT:
                    $renderer->doc.= '</div></div></div>';
                    break;
            }
            return true;
        }
        return false;
    }
}
