<?php
/**
 * DokuWiki Plugin Imagebox v2
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
 *     [200px imaegbox1{{wiki:dokuwiki-128.png|
 *     alternate text|caption or description
 *     }}]
 */
if(!defined('DOKU_INC')) die();

class syntax_plugin_imagebox2 extends DokuWiki_Syntax_Plugin {

    protected $mode;
    protected $pattern;

    function __construct() {
        $this->mode = substr(get_class($this), 7);

        // match patterns
        $this->pattern['entry'] = '\[(?:[\w ]+)?'
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
     * extended getImageSize() that supports svg file
     * 
     * @param string $filename  local or remote file
     * @return array that contains image size or false
     */
    protected function getImageSize($filename) {
        if (substr($filename, -4) == '.svg') {
            // OpenSSL changed in PHP 5.6.x
            // All encrypted client streams now enable peer verification by default.
            // allow self-signed ("snakeoil") certificates
            $context = stream_context_create(array(
                'ssl' => array('verify_peer' => false,'verify_peer_name' => false)
            ));

            $xml = @file_get_contents($filename, false, $context);
            if ($xml === false) {
                error_log('imagebox->getImageSize: file_get_contents failed for '.$filename);
                return false;
            }

            $xmlObject = simplexml_load_string($xml);
            if ($xmlObject === false) {
                error_log('imagebox->getImageSize: xml parse failed');
                return false;
            }
            $attr = $xmlObject->attributes();
            $w = (string) $attr->width;
            $h = (string) $attr->height;
            $info = array($w,$h);
        } else {
            $info = @getImageSize($filename);
        }
        return $info;
    }


    /**
     * handle syntax
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {
        switch($state){
            case DOKU_LEXER_ENTER:
                $m = array();
                list($params, $media) = explode('{{', $match, 2);

                // box params
                $params = substr($params, 1);
                if ($params && ($wrap = $this->loadHelper('wrap'))) {
                    $attr = $wrap->getAttributes(trim($params));
                    if (isset($attr['width'])) {
                        $m['box_size'] = $attr['width']; // imagebox width with unit
                    }
                    if (isset($attr['class'])) {
                        $m['box_style'] = str_replace('wrap_','',$attr['class']);
                    }
                }

                // media params
                $m += Doku_Handler_Parse_Media(rtrim($media,'|'));

                list($src, $hash) = explode('#', $m['src'], 2);
                list($ext, $mime) = mimetype($src);

                if ($m['type'] == 'internalmedia') {
                    global $ID;
                    $exists = false;
                    resolve_mediaid(getNS($ID), $src, $exists);
                    //$m['detail'] = ml($src,array('id'=>$ID,'cache'=>$m['cache']),($m['linking']=='direct'));
                    $m['src'] = ($hash) ? $src.'#'.$hash : $src;
                    $m['exist'] = $exists;
                    if ($exists && substr($mime,0,5) == 'image') {
                        $gimgs = $this->getImageSize(mediaFN($src));
                    }
                } else {
                    //$m['detail'] = ml($src, array('cache'=>'cache'), false);
                    if (substr($mime,0,5) == 'image') {
                        $gimgs = $this->getImageSize($src);
                        $m['exist'] = ($gimgs !== false);
                    } else {
                        $m['exist'] = false;
                    }
                }

                //if ($hash) {
                //    $m['detail'] .= '#'.$hash;
                //}

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
                //if (!$dispMagnify) $m['detail'] = false;
                $m['detail'] = $dispMagnify;

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
                // imaegbox style
                $boxStyle = isset($m['box_style']) ? $m['box_style'] : $this->getConf('default_box_style');

                // imagebox is border-box model that includes content, padding and border
                if ($m['box_size']) {
                    $boxWidth = $m['box_size'];
                } elseif ($m['width']) {
                    $boxWidth = ($m['width']+(1+3+1+2)*2).'px';
                } else {
                    $boxWidth = 'auto';
                }
                $renderer->doc.= '<div class="plugin_imagebox '.$boxStyle.' plugin_wrap wrap_'.$m['align']
                                .'" style="width: '.$boxWidth.';">';
                $renderer->doc.= '<div class="thumbinner">';

                // picture image
                if ($m['exist']) {
                    $renderer->{$m['type']}($m['src'],$m['title'],'box',$m['width'],$m['height'],$m['cache'],$m['linking']);
                } else {
                    $renderer->doc.= '<div class="error">Invalid image</div>';
                }
                // image caption
                $renderer->doc.= '<div class="thumbcaption">';
                if ($m['detail']) {
                    list($src, $hash) = explode('#', $m['src'], 2);

                    if ($m['type'] == 'internalmedia') {
                        $url = ml($src,array('id'=>$ID,'cache'=>$m['cache']),($m['linking']=='direct'));
                    } else {
                        $url = ml($src, array('cache'=>'cache'), false);
                    }
                    if ($hash) $url.= '#'.$hash;

                    $renderer->doc.= '<div class="magnify">';
                    $renderer->doc.= '<a class="internal" title="'.$this->getLang('enlarge').'" href="'.$url.'">';
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
