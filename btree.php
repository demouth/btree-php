<?php

// google/btree implements by PHP
// https://github.com/google/btree/blame/7364763242911ab6d418d2722e237194938ebad0/btree.go
//
// BTree
//   |- FreeList
//   |    |- node
//   |    |-  :
//   |    `- node
//   `- node
//        |- BTree
//        |- items
//        |    |- Int
//        |    |-  :
//        |    `- Int
//        `- children
//             |- node
//             |-  :
//             `- node


/**
 * Interface Item
 */
interface Item {
    public function get();
    public function Less(Item $item) : bool;
}

class Number implements Item
{
    /**
     * @var int
     */
    protected $value;

    public function __construct($value)
    {
        $this->value = (int)$value;
    }

    public function get() : int
    {
        return $this->value;
    }

    public function Less(Item $item) : bool
    {
        return $this->value < (int)$item->get();
    }
}

class Items implements Countable , ArrayAccess , IteratorAggregate
{
    /**
     * @var Item[]
     */
    protected $items = [];

    public function insertAt(int $index, Item $item)
    {
        array_splice($this->items, $index, 0, [$item]);
    }

    /**
     * @param int $index
     * @return Item
     */
    public function removeAt(int $index) : Item
    {
        $spliced = array_splice($this->items, $index, 1);
        return $spliced[0];
    }

    /**
     * @return Item
     */
    public function pop()
    {
        return array_pop($this->items);
    }

    /**
     * find returns the index where the given item should be inserted into this
     * list.  'found' is true if the item already exists in the list at the given
     * index.
     *
     * @param Item $item
     * @return array [index int, found bool]
     */
    public function find(Item $item) : array
    {
        $i = 0;
        $found = false;
        foreach ($this->items as $i => $_item) {
            if($item->Less($_item)){
                $found = true;
                break;
            }
        }
        if(!$found) $i++;
        if($i>0 && !$this->items[$i-1]->Less($item)) {
            return [$i-1, true];
        }
        return [$i, false];
    }

    //-ArrayAccess----------

    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    public function offsetExists($offset) {
        return isset($this->items[$offset]);
    }

    public function offsetUnset($offset) {
        unset($this->items[$offset]);
    }

    public function offsetGet($offset) {
        return isset($this->items[$offset]) ? $this->items[$offset] : null;
    }

    //-Countable----------

    public function count() : int
    {
        return count($this->items);
    }
    //-IteratorAggregate----------
    public function getIterator()
    {
        return new ArrayIterator($this->items);
    }

    //-----------

    public function get()
    {
        return $this->items;
    }
    public function set(array $items)
    {
        $this->items = $items;
    }
    public function append(array $array)
    {
        $this->items = array_merge($this->items, $array);
    }
    public function slice(int $offset, ?int $length=null)
    {
        if($length===null) $length = count($this->items);
        return array_slice($this->items, $offset, $length);
    }
    public function __toString()
    {
        $string = '';
        foreach ($this->items as $key => $item){
            $string .= $item->get().' ';
        }
        return $string;
    }
}


// node is an internal node in a tree.
//
// It must at all times maintain the invariant that either
//   * len(children) == 0, len(items) unconstrained
//   * len(children) == len(items) + 1
class Node
{
    /**
     * @var Items
     */
    public $items;
    /**
     * @var Children
     */
    public $children;
    /**
     * @var BTree
     */
    public $t;

    public function __construct()
    {
        $this->items = new Items();
        $this->children = new Children();
    }

    /**
     * split splits the given node at the given index.  The current node shrinks,
     * and this function returns the item that existed at that index and a new node
     * containing all items/children after it.
     * 
     * 
     * @param int $i
     * @return array
     */
    public function split(int $i) : array
    {
        $item = $this->items[$i];
        $next = $this->t->newNode();
        $next->items->append($this->items->slice($i+1));
        $this->items->set($this->items->slice(0, $i+1));
        if($this->children->count() > 0){
            $next->children->append($this->children->slice($i+1));
            $this->children->set($this->children->slice(0, $i+1));
        }
        return [$item, $next];
    }

    // maybeSplitChild checks if a child should be split, and if so splits it.
    // Returns whether or not a split occurred.
    protected function maybeSplitChild(int $i , int $maxItems) : bool
    {
        if($this->children[$i]->items->count() < $maxItems) {
            return false;
        }
        $first = $this->children[$i];
        list($item, $second) = $first->split($maxItems / 2);
        $this->items->insertAt($i, $item);
        $this->children->insertAt($i+1, $second);
        return true;
    }
    // insert inserts an item into the subtree rooted at this node, making sure
    // no nodes in the subtree exceed maxItems items.  Should an equivalent item be
    // be found/replaced by insert, it will be returned.
    public function insert(Item $item , int $maxItems) : ?Item
    {
        list($i, $found) = $this->items->find($item);
        if($found){
            $out = $this->items[$i];
            $this->items[$i] = $item;
            return $out;
        }
        if($this->children->count() === 0){
            $this->items->insertAt($i, $item);
            return null;
        }
        if($this->maybeSplitChild($i, $maxItems)){
            $inTree = $this->items[$i];
            if($item->Less($inTree)){
                // no change, we want first split node
            }else if($inTree->Less($item)){
                $i++;
            }else{
                $out = $this->items[$i];
                $this->items[$i] = $item;
                return $out;
            }
        }
        return $this->children[$i]->insert($item, $maxItems);
    }
    // get finds the given key in the subtree and returns it.
    public function get(Item $key) : ?Item
    {
        list($i, $found) = $this->items->find($key);
        if($found){
            return $this->items[$i];
        }else if($this->children->count() > 0){
            return $this->children[$i]->get($key);
        }
        return null;
    }
    // min returns the first item in the subtree.
    protected function min() : ?Item
    {
        $that = $this;
        while ($that->children->count() > 0){
            $that = $that->children[0];
        }
        if($that->items->count() === 0) {
            return null;
        }
        return $that->items[0];
    }
    // max returns the last item in the subtree.
    protected function max() : ?Item
    {
        $that = $this;
        while ($that->children->count() > 0){
            $that = $that->children[$that->children->count() - 1];
        }
        if($that->items->count() === 0) {
            return null;
        }
        return $that->items[$that->items->count() - 1];
    }
    // remove removes an item from the subtree rooted at this node.
    protected function remove(Item $item, int $minItems, ToRemove $typ) : Item
    {
        $i = 0;
        $found = false;
        switch ($typ->get()){
            case ToRemove::REMOVE_MAX :
                if ($this->children->count() === 0){
                    return$this->items->pop();
                }
                $i = $this->items->count();
                break;
            case ToRemove::REMOVE_MIN :
                if($this->children->count() === 0){
                    return $this->items->removeAt(0);
                }
                $i = 0;
                break;
            case ToRemove::REMOVE_ITEM :
                list($i, $found) = $this->items->find($item);
                if($this->children->count() === 0){
                    if($found){
                        return $this->items->removeAt($i);
                    }
                    return null;
                }
                break;
            default:
                throw new LogicException('invalid type');
                break;
        }
        $child = $this->children[$i];
        if($child->items->count() <= $minItems){
            return $this->growChildAndRemove($i, $item, $minItems, $typ);
        }
        if($found){
            $out = $this->items[$i];
            $this->items[$i] = $child->remove(null, $minItems, ToRemove::make(ToRemove::REMOVE_MAX));
            return $out;
        }
        return $child->remove($item, $minItems, $typ);
    }
    // growChildAndRemove grows child 'i' to make sure it's possible to remove an
    // item from it while keeping it at minItems, then calls remove to actually
    // remove it.
    //
    // Most documentation says we have to do two sets of special casing:
    //   1) item is in this node
    //   2) item is in child
    // In both cases, we need to handle the two subcases:
    //   A) node has enough values that it can spare one
    //   B) node doesn't have enough values
    // For the latter, we have to check:
    //   a) left sibling has node to spare
    //   b) right sibling has node to spare
    //   c) we must merge
    // To simplify our code here, we handle cases #1 and #2 the same:
    // If a node doesn't have enough items, we make sure it does (using a,b,c).
    // We then simply redo our remove call, and the second time (regardless of
    // whether we're in case 1 or 2), we'll have enough items and can guarantee
    // that we hit case A.
    protected function growChildAndRemove(int $i, Item $item, int $minItems, ToRemove $typ) : Item
    {
        $child = $this->children[$i];
        if($i > 0 && $this->children[$i-1]->items->count() > $minItems){
            $stealFrom = $this->children[$i-1];
            $stolenItem = $stealFrom->items->pop();
            $child->items->insertAt(0, $this->items[$i-1]);
            $this->items[$i-1] = $stolenItem;
            if($stealFrom->children->count() > 0){
                $child->children->insertAt(0, $stealFrom->children->pop());
            }
        }else if ($i < $this->items->count() && $this->children[$i+1]->items->count() > $minItems){
            $stealFrom = $this->children[$i+1];
            $stolenItem = $stealFrom->items->removeAt(0);
            $child->items->append([$this->items[$i]]);
            $this->items[$i] = $stolenItem;
            if ($stealFrom->children->count() > 0){
                $child->children->append([$stealFrom->children->removeAt(0)]);
            }
        }else{
            if($i >= $this->items->count()){
                $i--;
                $child = $this->children[$i];
            }
            $mergeItem = $this->items->removeAt($i);
            $mergeChild = $this->children->removeAt($i+1);
            $child->items->append([$mergeItem]);
            $child->items->append($mergeChild->items->get());
            $child->children->append($mergeChild->children->get());
            $this->t->freeNode($mergeChild);
        }
        return $this->remove($item, $minItems, $typ);
    }
    // iterate provides a simple method for iterating over elements in the tree.
    //
    // When ascending, the 'start' should be less than 'stop' and when descending,
    // the 'start' should be greater than 'stop'. Setting 'includeStart' to true
    // will force the iterator to include the first item when it equals 'start',
    // thus creating a "greaterOrEqual" or "lessThanEqual" rather than just a
    // "greaterThan" or "lessThan" queries.
    protected function iterate(Direction $dir, Item $start, Item $stop, bool $includeStart, bool $hit, $iter)
    {
        //TODO
    }
    // Used for testing/debugging purposes.
    public function print($level)
    {
        $string = '';
        $string .= str_repeat('    ', $level).$this->items;
        $string .= "\n";
        foreach ($this->children as $key => $child){
            $string .= $child->print($level+1);
        }
        return $string;
    }
}
// toRemove details what item to remove in a node.remove call.
class ToRemove
{
    // removes the given item
    const REMOVE_ITEM = 1;
    // removes smallest item in the subtree
    const REMOVE_MIN = 2;
    // removes largest item in the subtree
    const REMOVE_MAX = 3;
    protected $value;
    public static function make(int $value)
    {
        $self = new static();
        $self->value = $value;
        return $self;
    }
    public function get() : int
    {
        return $this->value;
    }
}
class Direction
{
    const DESCEND = -1;
    const ASCEND = 1;
    protected $value;
    public static function make(int $value)
    {
        $self = new static();
        $self->value = $value;
        return $self;
    }
    public function get() : int
    {
        return $this->value;
    }
}

class Children implements Countable , ArrayAccess , IteratorAggregate
{
    /**
     * @var Node[]
     */
    protected $children = [];

    public function insertAt(int $index, Node $node)
    {
        array_splice($this->children, $index, 0, [$node]);
    }

    /**
     * @param int $index
     * @return Node
     */
    public function removeAt(int $index) : Node
    {
        $spliced = array_splice($this->children, $index, 1);
        return $spliced[0];
    }

    /**
     * @return Item
     */
    public function pop() : Node
    {
        return array_pop($this->children);
    }

    //-ArrayAccess----------

    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $this->children[] = $value;
        } else {
            $this->children[$offset] = $value;
        }
    }

    public function offsetExists($offset) {
        return isset($this->children[$offset]);
    }

    public function offsetUnset($offset) {
        unset($this->children[$offset]);
    }

    public function offsetGet($offset) {
        return isset($this->children[$offset]) ? $this->children[$offset] : null;
    }
    //-Countable----------

    public function count() : int
    {
        return count($this->children);
    }
    //-IteratorAggregate----------
    public function getIterator()
    {
        return new ArrayIterator($this->children);
    }

    //-------------------

    public function get()
    {
        return $this->children;
    }
    public function set(array $children) : void
    {
        $this->children = $children;
    }
    public function append(array $array)
    {
        $this->children = array_merge($this->children, $array);
    }

    public function slice(int $offset, ?int $length=null) : array
    {
        if($length===null) $length = count($this->children);
        return array_slice($this->children, $offset, $length);
    }
}

// FreeList represents a free list of btree nodes. By default each
// BTree has its own FreeList, but multiple BTrees can share the same
// FreeList.
// Two Btrees using the same freelist are not safe for concurrent write access.
class FreeList
{
    /**
     * @var Node[]
     */
    protected $freelist = [];
    protected $size = 0;

    /**
     * FreeList constructor.
     * @param int $size
     */
    public function __construct(int $size)
    {
        //$size is freelist length.
        $this->freelist = [];
        $this->size = 0;
    }

    /**
     * @return Node
     */
    public function newNode() : Node
    {
        $index = count($this->freelist) - 1;
        if($index < 0) {
            return new Node();
        }
        $this->freelist = array_slice($this->freelist, 0, $index);

        return $this->freelist[$index];
    }
    public function freeNode(Node $n)
    {
        if(count($this->freelist)<$this->size){
            $this->freelist[] = $n;
        }
    }
}
// BTree is an implementation of a B-Tree.
//
// BTree stores Item instances in an ordered structure, allowing easy insertion,
// removal, and iteration.
//
// Write operations are not safe for concurrent mutation by multiple
// goroutines, but Read operations are.
class BTree
{
    /**
     * @var int
     */
    protected $degree;
    /**
     * @var int
     */
    protected $length = 0;
    /**
     * @var FreeList
     */
    protected $freelist;
    /**
     * @var Node
     */
    public $root;

    const DefaultFreeListSize = 32;

    /**
     * BTree constructor.
     * @param int $degree
     * @throws Exception
     */
    public function __construct(int $degree)
    {
        if($degree <= 1) throw new Exception('bad degree');
        $this->degree = $degree;
        $this->freelist = static::NewFreeList(static::DefaultFreeListSize);
    }

    public function maxItems() : int
    {
        return $this->degree*2 - 1;
    }
    public function minItems() : int
    {
        return $this->degree - 1;
    }
    public function newNode() : Node
    {
        $n = $this->freelist->newNode();
        $n->t = $this;
        return $n;
    }
    public function freeNode(Node $n)
    {
        $n->items = [];
        $n->children = [];
        $n->t = null;
        $this->freelist->freeNode($n);
    }
    // ReplaceOrInsert adds the given item to the tree.  If an item in the tree
    // already equals the given one, it is removed from the tree and returned.
    // Otherwise, nil is returned.
    //
    // nil cannot be added to the tree (will panic).
    public function ReplaceOrInsert(Item $item) : ?Item
    {
        if($this->root === null){
            $this->root = $this->newNode();
            $this->root->items->append([$item]);
            $this->length++;
            return null;
        }else if($this->root->items->count() >= $this->maxItems()){
            list($item2,$second) = $this->root->split($this->maxItems() / 2);
            $oldroot = $this->root;
            $this->root = $this->newNode();
            $this->root->items->append([$item2]);
            $this->root->children->append([$oldroot, $second]);
        }
        $out = $this->root->insert($item, $this->maxItems());
        if($out === null){
            $this->length++;
        }
        return $out;
    }
    public function Get(Item $key) : ?Item
    {
        if($this->root === null){
            return null;
        }
        return $this->root->get($key);
    }
    public function Has(Item $key) : bool
    {
        return $this->Get($key) != null;
    }
    public function Min() : ?Item
    {
        return static::_min($this->root);
    }
    public function Max() : ?Item
    {
        return static::_max($this->root);
    }
    public function Len() : int
    {
        return $this->length;
    }
    public function Delete()
    {
        //TODO
    }
    public function DeleteMin()
    {
        //TODO
    }
    public function DeleteMax()
    {
        //TODO
    }
    protected static function _min(Node $n) : ?Item
    {
        if(!$n) {
            return null;
        }
        while ($n->children->count() > 0){
            $n = $n->children[0];
        }
        if($n->items->count()===0){
            return null;
        }
        return $n->items[0];
    }
    protected static function _max(Node $n) : ?Item
    {
        if(!$n) {
            return null;
        }
        while ($n->children->count() > 0){
            $n = $n->children[$n->children->count()-1];
        }
        if($n->items->count()===0){
            return null;
        }
        return $n->items[$n->items->count()-1];
    }
    public static function NewWithFreeList()
    {
        //TODO
    }

    /**
     * @param int $size
     * @return FreeList
     */
    protected static function NewFreeList(int $size)
    {
        return new FreeList($size);
        //return &FreeList{freelist: make([]*node, 0, size)}
    }
}