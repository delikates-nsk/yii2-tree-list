<?php
namespace delikatesnsk\treelist;

class TreeListWidget extends \yii\base\Widget
{
    public $form = null; //ActiveForm
    public $model = null; //model
    public $attribute = null; //model attribute
    public $multiSelect = false; //true or false
    public $searchPanel = [ 'visible' => false,
                            'label' => '', //text before search input 
                            'placeholder' => '',  //serch input placeholder text
                            'searchCaseSensivity' => false 
                          ];
    public $selectedItemsPanel = [ 'visible' => false,
                                   'showRemoveButton' => false
                                 ];
    public $rootNode = [
                          'visible' => true,
                          'canSelect' => false,
                          'id' => 'root',
                          'label' => 'Root' 
                       ];

    public $expand = false; //expand dropdown tree after show
    public $ajax = null;
    //[
    //  'onNodeExpand' => [
    //                  'url' => '', URL for ajax request
    //                  'method' => 'post', //post or get
    //                  'params' => [
    //                                  'param1' => 'value1',
    //                                  'param2' => 'value1',
    //                                  'param3' => 'value1',
    //                                   ...
    //                                  'paramN' => 'valueN',
    //                                  'someparamName' => '%nodeId' // <-- %nodeId replaced to id of current node
    //                              ]
    //                  //returned Data will be array of array
    //                  //[
    //                  //  [
    //                  //      'id' =>
    //                  //      'label' =>
    //                  //      'items' => [
    //                  //                  [
    //                  //                      'id' =>
    //                  //                      'label' =>
    //                  //                  ],
    //                  //                  ... more
    //                  //                 ]
    //                  //  ],
    //                  //  ... more
    //                  //]
    //                ],
    //  'onNodeCollapse' => [
    //                      ... see OnExpand, but the returned data will not be processed, only send ajax request
    //                  ],
    //]
    public $onItemSelect = null; //javascript callback function that will be runned when tree item selected
    //  'onItemSelect' => '', //javascript callback function that will be runned when tree item selected
    //    function(item) {
    //       console.log( item ); //selected item object
    //    }

    public $items = null; //array of tree nodes with subnodes

    private $html = '';
    private $treeObject = null;

    private function isFunction( $code ) {
        $result = false;
        $code = preg_replace('/[\x0A]/', '', $code);
        $code = preg_replace('/[\x0D]/', '', $code);
        preg_match_all('/^function[\s]?\(.*?\)/', mb_convert_case( $code, MB_CASE_LOWER), $matches);
        if ( is_array( $matches ) && count( $matches ) == 1 && is_array( $matches[0] ) && count( $matches[0] ) == 1 ) {
            $matches = [];
            preg_match_all('/\{(.*?)\}/',$code, $matches);
            $result =  ( is_array( $matches ) && count( $matches ) > 0 && is_array( $matches[0] ) && count( $matches[0] ) == 1 );
        }
        return $result;
    }

    private function buildTreeObject( $items, &$parentItem = null ) {
        if ( is_array( $items ) ) {
            foreach ($items as $item) {
                if ( is_array( $item ) && isset( $item['id'] ) && isset( $item['label'] ) ) {
                    $node = new \stdClass();
                    $node->parent = $parentItem;
                    $node->id = $item['id'];
                    $node->label = $item['label'];
                    if ( isset( $item['items'] ) && is_array( $item['items'] ) && ( count( $item['items'] ) > 0 || $this->ajax !== null ) ) {
                        $node->items = [];
                        $this->buildTreeObject( $item['items'], $node );
                    }
                    $parentItem->items[] = $node;
                }
            }
        }
    }

    public function buildTreeView( $items ) {
        if ( is_array( $items ) && count( $items ) > 0 ) {
            foreach( $items as $index => $item ) {
                if (is_object( $item ) && isset( $item->id ) && isset( $item->label ) ) {
                    if ( $index == 0 ) {
                        //Если parent у item последний Node у своего parent добавляем класс last-node
                        $class =  ( isset( $item->parent ) && $item->parent !== null && isset( $item->parent->parent ) && $item->parent->parent !== null && $item->parent->parent->items[ count( $item->parent->parent->items ) - 1] == $item->parent ?  " class=\"last-node\"" : "" );
                        $this->html .= "<ul".$class.">\n";
                    }

                    $this->html .= "<li".( isset( $item->items ) && is_array( $item->items ) ? " class=\"parent\"" : "" ).">\n";
                    $this->html .= "    <div class=\"node\">\n";
                    $this->html .= "        ".( isset( $item->items ) && is_array( $item->items ) && ( count( $item->items ) > 0 || $this->ajax !== null ) ? "<i class=\"fa fa-plus-square-o\"></i>\n" : "" );
                    $this->html .= "        ".( $this->multiSelect ? "<i class=\"fa fa-square-o\"></i>" : "" );
                    $this->html .= "        <span".( ( isset( $item->id ) ? " data-id='".$item->id."'" : "" ) ).">".( isset( $item->label ) ? $item->label : "&nbsp;" )."</span>\n";
                    $this->html .= "    </div>\n";
                    if ( isset( $item->items ) && is_array( $item->items ) && ( count( $item->items ) > 0 || $this->ajax !== null )  ) {
                        $this->buildTreeView( $item->items );
                    }
                    $this->html .= "</li>\n";

                    if ( $index == count( $items ) - 1 ) {
                        $this->html .= "</ul>\n";
                    }
                }
            }
        }
    }

    public function init()
    {
        parent::init();
        $this->onItemSelect = (  isset( $this->onItemSelect ) && $this->onItemSelect !== null ? ( trim( $this->onItemSelect ) != '' ? ( $this->isFunction( $this->onItemSelect ) ? $this->onItemSelect : null ) : null ) : null );
        $this->selectedItemsPanel = ( !isset( $this->selectedItemsPanel ) ||  !is_array( $this->selectedItemsPanel ) || ( !isset( $this->selectedItemsPanel['visible'] ) && !isset( $this->selectedItemsPanel['showRemoveButton'] ) ) ? [ 'visible' => false, 'showRemoveButton' => false ] : $this->selectedItemsPanel  );
        $this->selectedItemsPanel['visible'] = ( isset( $this->selectedItemsPanel['visible'] ) && is_bool( $this->selectedItemsPanel['visible'] ) ? $this->selectedItemsPanel['visible'] : false );
        $this->selectedItemsPanel['showRemoveButton'] = ( isset( $this->selectedItemsPanel['showRemoveButton'] ) && is_bool( $this->selectedItemsPanel['showRemoveButton'] ) ? $this->selectedItemsPanel['showRemoveButton'] : false );
        $this->treeObject = new \stdClass();
        $this->treeObject->id = -1;
        $this->treeObject->label = 'Root';
        $this->treeObject->items = [];
        $this->buildTreeObject($this->items, $this->treeObject );
        $this->buildTreeView( $this->treeObject->items );
    }

    public function run()
    {
        return $this->render('view', ['htmlData' => $this->html]);
    }
}