<?php
namespace App\Model;

use App\Code\StatusCode;
use App\Connector\MySQL;
use App\Provider\Model;
use App\Provider\Security;
use App\Provider\User;
use App\Type\AttributeGroupType;
use App\Type\UserType;

class CategoriesModel extends BaseModel
{

    private function _getDepth($categoryId, $depth = 0)
    {
        $parentId = MySQL::get()->fetchColumn('SELECT parent_id FROM pages_category WHERE id = :pId', [
            'pId' => $categoryId
        ]);

        if ($parentId != 0)
        {
            $depth++;
            return $this->_getDepth($parentId, $depth);
        }
        else
        {
            return $depth;
        }
    }

    public function getAll()
    {
        $sql = 'SELECT * FROM pages_category';
        $data = MySQL::get()->fetchAll($sql);
        return $data;
    }

    public function buildTree($categories, $parentId = 0, $withPages = false)
    {
        $tree = [];

        foreach ($categories as $row)
        {
            if ($row['parent_id'] == $parentId)
            {
                $child = $this->buildTree($categories, $row['id'], $withPages);
                $data = ['name' => $row['name'], 'childs' => $child, 'id' => $row['id']];
                if ($withPages)
                {
                    $data['pages'] = Model::get('pages')->getAllByCategoryId($row['id']);
                }

                $tree[] = $data;
            }
        }

        return $tree;
    }

    public function create($name, $parentId = 0)
    {
        // check depth
        if ($parentId != 0)
        {
            if ($this->_getDepth($parentId) > 1)
            {
                return StatusCode::CATEGORY_MAX_DEPTH;
            }
        }

        $sql = 'INSERT INTO pages_category (`name`, parent_id) VALUES (:n, :pId)';
        MySQL::get()->exec($sql, [
            'n' => $name,
            'pId' => $parentId
        ]);

        return true;
    }

    public function update($id, $name)
    {
        $sql = 'UPDATE pages_category SET `name` = :n WHERE id = :id';
        MySQL::get()->exec($sql, [
            'n' => $name,
            'id' => $id
        ]);
    }

    public function delete($id)
    {
        // check if he is parent
        $parentSQL = 'SELECT * FROM pages_category WHERE parent_id = :id';
        $parentCategories = MySQL::get()->fetchAll($parentSQL, [
            'id' => $id
        ]);

        if (count($parentCategories) > 0)
        {
            return StatusCode::CATEGORY_IS_PARENT; // only empty category can delete
        }

        // get pages with category
        $sql = 'SELECT * FROM pages WHERE category_id = :id';
        $pages = MySQL::get()->fetchAll($sql, ['id' => $id]);
        if (count($pages) > 0) // delete pages
        {
            $deleteIds = [];
            foreach($pages as $page)
            {
                $deleteIds[] = $page['id'];
            }

            $deleteIds = implode(',', $deleteIds);

            // delete pages
            MySQL::get()->exec('DELETE FROM pages_content WHERE page_id IN ('. $deleteIds .')');
            MySQL::get()->exec('DELETE FROM pages WHERE id IN ('. $deleteIds .')');
        }

        // delete category
        MySQL::get()->exec('DELETE FROM pages_category WHERE id = :id', [
            'id' => $id
        ]);
        return true;
    }
}