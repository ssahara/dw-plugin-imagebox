<?php
/**
 * DokuWiki Plugin Imagebox v2; Action component
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Satoshi Sahara <sahara.satoshi@gmail.com>
 */

if(!defined('DOKU_INC')) die();

class action_plugin_imagebox2 extends DokuWiki_Action_Plugin {

    /**
     * register the event handlers
     */
    public function register(Doku_Event_Handler $controller) {

        $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE', $this, '_handleMeta');
    }

    /**
     * Bind the swipebox behaviour on image links
     */
    public function _handleMeta(Doku_Event $event, $params) {

        if (plugin_isdisabled('gallery')) return;

        $event->data['script'][] = array(
            'type'     => 'text/javascript',
            'charrset' => 'utf-8',
            'src'      => DOKU_BASE.'lib/plugins/imagebox2/swipebox.js',
            '_data'    => '',
        );
    }

}
