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

        if ($parentId != -1)
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
        $categories = [];
        foreach($data as $row)
        {
            $categories[$row['id']] = $row;
        }

        foreach($categories as $category)
        {
            $category['parent'] = [
                'id' => $category['parent_id'],
                'name' => $categories[$category['parent_id']]
            ];
        }

        return $categories;
    }

    public function create($name, $parentId = -1)
    {
        // check depth
        if ($parentId != -1)
        {
            if ($this->_getDepth($parentId) > 2)
            {
                return StatusCode::CATEGORY_MAX_DEPTH;
            }
        }

        $sql = 'INSERT INTO pages_category (`name`, parent_id) VALUES (:n, :pId)';
        MySQL::get()->exec($sql, [
            'n' => $name,
            'pId' => $parentId
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