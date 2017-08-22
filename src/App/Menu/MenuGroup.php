<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 22.08.17
 * Time: 23:31
 */

namespace App\Menu;


class MenuGroup
{
    private $name;

    /**
     * @var MenuItem[]
     */
    private $items;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function add(MenuItem $item)
    {
        $this->items[] = $item;
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getItems()
    {
        return $this->items;
    }
}