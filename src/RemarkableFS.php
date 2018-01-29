<?php

namespace splitbrain\RemarkableAPI;

/**
 * Class RemarkableFS
 *
 * Implements helpers to simulate a file system structure on top of the flat hierarchy of the ReMarkable
 *
 * @package splitbrain\RemarkableAPI
 */
class RemarkableFS
{
    protected $index = [];
    protected $tree = [];

    public function __construct($list)
    {
        // index by id
        foreach ($list as $item) {
            $this->index[$item['ID']] = $item;
        }

        // add path
        foreach ($this->index as $id => $item) {
            $this->calcPath($id);
        }

        // create a tree index (each path can have multiple items)
        foreach ($this->index as $id => $item) {
            $p = $item['Path'];
            if (!isset($this->tree[$p])) $this->tree[$p] = [];
            $this->tree[$p][] = $item;
        }
        ksort($this->tree);
    }

    /**
     * Access the tree based index
     *
     * @return array
     */
    public function getTree() {
        return $this->tree;
    }

    /**
     * Get a uniocode icon for the given type
     *
     * @fixme figure out all the types
     * @param string $type
     * @return $string;
     */
    public function typeToIcon($type) {
        switch($type) {
            case 'CollectionType': return '📁';
            case 'DocumentType': return '📄';
            case 'BookmarkType': return '🔖'; # I just guessed this one
            default: return '❓';
        }
    }

    /**
     * Sets the path attribute to the given ID in the index
     *
     * @param string $id
     * @return string
     * @throws \Exception
     */
    protected function calcPath($id)
    {
        if (!isset($this->index[$id])) throw new \Exception("Unknown ID $id. Inconsitent meta data");
        $item =& $this->index[$id];

        // path already set?
        if (isset($item['Path'])) return $item['Path'];

        if ($item['Parent'] === '') {
            // top level item
            $item['Path'] = '/' . $item['VissibleName'];
        } else {
            // recursion
            $item['Path'] = $this->calcPath($item['Parent']) . '/' . $item['VissibleName'];
        }

        return $item['Path'];
    }
}