<?php

namespace SilverStripe\Cow\Model\Modules;

use Traversable;
use IteratorAggregate;

class LibraryList implements IteratorAggregate
{
    /**
     * List of items
     *
     * @var Library[]
     */
    protected $items;

    /**
     * LibraryList constructor.
     *
     * @param array|LibraryList $items
     */
    public function __construct($items = [])
    {
        if ($items instanceof LibraryList) {
            $items = $items->getItems();
        }
        $this->setItems($items);
    }

    /**
     * @return Traversable|Library[]
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * @return Library[]
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @param Library[] $items
     * @return $this
     */
    public function setItems($items)
    {
        $this->items = [];
        foreach($items as $item) {
            $this->items[$item->getName()] = $item;
        }
        return $this;
    }

    /**
     * @param Library $item
     * @return $this
     */
    public function add($item) {
        $this->items[$item->getName()] = $item;
        return $this;
    }

    /**
     * Create a new list with this and another list merged.
     * Does not modify original list
     *
     * @param mixed $items
     * @return LibraryList
     */
    public function merge($items) {
        $result = clone $this;
        foreach($items as $item) {
            $result->add($item);
        }
        return $result;
    }

    /**
     * @param string $name
     * @return Library
     */
    public function getByName($name) {
        if (isset($this->items[$name])) {
            return $this->items[$name];
        }
    }

    /**
     * @param array $names Optional list of modules to filter
     * @param bool $listIsExclusive Set to true if this list is exclusive
     * @return static
     */
    public function filter($names, $listIsExclusive = false) {
        if (empty($names)) {
            return $this;
        }

        $items = $this->getItems();
        $included = [];
        foreach ($items as $library) {
            $name = $library->getName();
            if ((in_array($name, $names) === (bool)$listIsExclusive)) {
                $included[] = $library;
            }
        }
        return new LibraryList($included);
    }
}
