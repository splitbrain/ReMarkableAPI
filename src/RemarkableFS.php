<?php

namespace splitbrain\RemarkableAPI;

/**
 * Class RemarkableFS
 *
 * Implements helpers to simulate a file system structure on top of the flat hierarchy of
 * the reMarkable. It maintains a state of what is available in the cloud in an index. That
 * index needs to be refreshed when needed.
 *
 * @package splitbrain\RemarkableAPI
 */
class RemarkableFS
{
    protected $api;
    protected $index = [];
    protected $tree = [];

    /**
     * RemarkableFS constructor.
     *
     * @param RemarkableAPI $api The authenticated API
     */
    public function __construct(RemarkableAPI $api)
    {
        $this->api = $api;
        $this->refreshIndex();
    }

    /**
     * Fetch item info from the cloud again
     *
     * You need to call this whenever you modify the data on the cloud through the
     * API directly (or when data gets synced to the cloud from elsewhere)
     */
    public function refreshIndex()
    {
        $this->index = [];
        $this->tree = [];
        $list = $this->api->listItems();

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
    public function getTree()
    {
        return $this->tree;
    }

    /**
     * This returns the ID of the end folder of the given folder hierarchy
     *
     * Missing folders are created if necessary
     *
     * @param string $folder A folder hierarchy in unix notation
     * @return string the ID, empty for the top level
     */
    public function mkdirP($folder)
    {
        $folder = trim($folder, '/');
        if ($folder === '') return '';
        $parts = explode('/', $folder);

        $current = '';
        $parent = '';
        foreach ($parts as $part) {
            $current .= '/' . $part;
            $item = $this->findFirst($current, RemarkableAPI::TYPE_COLLECTION);
            if ($item === null) {
                $parent = $this->mkdir($part, $parent);
            } else {
                $parent = $item['ID'];
            }
        }

        return $parent;
    }

    /**
     * Create a new folder and add it to the index
     *
     * @param string $folder Name of the new folder
     * @param string $parent The ID of the parent folder
     * @return string the ID of the new folder
     */
    protected function mkdir($folder, $parent = '')
    {
        // create
        $item = $this->api->createFolder($folder, $parent);
        // fetch full info about newly created item
        $item = $this->api->getItem($item['ID']);

        // update the index
        $this->index[$item['ID']] = $item;
        $this->calcPath($item['ID']);
        return $item['ID'];
    }

    /**
     * Find the first item matching the given path and type
     *
     * @param string $path
     * @param string $type
     * @return null|array
     */
    public function findFirst($path, $type)
    {
        if (!isset($this->tree[$path])) return null;
        foreach ($this->tree[$path] as $item) {
            if ($item['Type'] == $type) return $item;
        }
        return null;
    }

    /**
     * Get a uniocode icon for the given type
     *
     * @fixme figure out all the types
     * @param string $type
     * @return string;
     */
    public function typeToIcon($type)
    {
        switch ($type) {
            case 'CollectionType':
                return '📁';
            case 'DocumentType':
                return '📄';
            default:
                return '❓';
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