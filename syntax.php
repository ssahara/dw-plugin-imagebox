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

                $dispMagnify = ($m['width'] || $m['height'])
                             && ($this->getConf('display_magnify') == 'If necessary')
                             || ($this->getConf('display_magnify') == 'Always');

                $gimgs = false;

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

                    if ($exists) $gimgs = @getImageSize(mediaFN($src));
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

                if (!$m['width'] && $m['exist']) {
                    ($m['height'])?
                    $m['h'] = $m['height'] * $gimgs[0]/$gimgs[1]:
                    $m['w'] = $gimgs[0];
                }
                if (isset($m['w'])) $m['width'] = $m['w'];
                if (isset($m['h'])) $m['height'] = $m['h'];

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
                $renderer->doc.= '<div class="thumb2 t'.$m['align'].'">';
                $renderer->doc.= '<div class="thumbinner">';
                if ($m['exist']) {
                    $renderer->{$m['type']}($m['src'],$m['title'],'box2',$m['width'],$m['height'],$m['cache'],$m['linking']);
                } else {
                    $renderer->doc.= 'Invalid Link';
                }
                $renderer->doc.= '<div class="thumbcaption" style="max-width: '.($m['width']-6).'px">';
                if ($m['detail']) {
                    $renderer->doc.= '<div class="magnify">';
                    $renderer->doc.= '<a class="internal" title="'.$this->getLang('enlarge').'" href="'.$m['detail'].'">';
                    $renderer->doc.= '<img width="15" height="11" alt="" src="'.DOKU_REL.'lib/plugins/imagebox/magnify-clip.png"/>';
                    $renderer->doc.= '</a></div>';
                }
                break;

            case DOKU_LEXER_UNMATCHED:
                $match = $m;
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
}
