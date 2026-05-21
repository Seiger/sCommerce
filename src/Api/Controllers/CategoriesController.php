<?php namespace Seiger\sCommerce\Api\Controllers;

use EvolutionCMS\Facades\UrlProcessor;
use Illuminate\Http\Request;
use Seiger\sApi\Http\ApiResponse;
use Seiger\sCommerce\Facades\sCommerce;
use Seiger\sCommerce\Models\sCategory;

final class CategoriesController
{
    public function index(Request $request)
    {
        $active = $request->query('active', '1');
        if (!is_scalar($active) || !in_array((string)$active, ['0', '1', 'all'], true)) {
            return ApiResponse::error('Validation error.', 422, (object)[
                'errors' => [
                    'active' => 'Must be 0, 1 or all.',
                ],
            ]);
        }

        $depth = (int)$request->query('depth', 10);
        if ($depth < 0) {
            $depth = 0;
        }
        if ($depth > 20) {
            $depth = 20;
        }

        $rootId = (int)sCommerce::config('basic.catalog_root', evo()->getConfig('site_start', 1));
        $categories = $this->loadCategories($rootId, $depth, (string)$active);

        return ApiResponse::success([
            'results' => $categories,
            'total' => count($categories),
            'root_id' => $rootId,
        ], '');
    }

    private function loadCategories(int $rootId, int $depth, string $active): array
    {
        $results = [];
        $parentIds = [$rootId];
        $level = 0;

        $root = $this->baseQuery($active)
            ->where('id', $rootId)
            ->first();

        if ($root) {
            $results[] = $this->mapCategory($root, 0);
        }

        while ($level < $depth && count($parentIds)) {
            $children = $this->baseQuery($active)
                ->whereIn('parent', $parentIds)
                ->orderBy('parent')
                ->orderBy('menuindex')
                ->orderBy('id')
                ->get();

            if (!$children->count()) {
                break;
            }

            $parentIds = [];
            $level++;

            foreach ($children as $category) {
                $results[] = $this->mapCategory($category, $level);
                $parentIds[] = (int)$category->id;
            }
        }

        return $results;
    }

    private function baseQuery(string $active)
    {
        $query = sCategory::query()
            ->select([
                'id',
                'parent',
                'pagetitle',
                'longtitle',
                'menutitle',
                'alias',
                'published',
                'deleted',
                'hidemenu',
                'menuindex',
                'isfolder',
            ]);

        if ($active === '1') {
            $query->where('published', 1)->where('deleted', 0);
        } elseif ($active === '0') {
            $query->where(function ($query) {
                $query->where('published', 0)->orWhere('deleted', 1);
            });
        }

        return $query;
    }

    private function mapCategory(sCategory $category, int $level): array
    {
        return [
            'id' => (int)$category->id,
            'parent_id' => (int)$category->parent,
            'title' => (string)$category->pagetitle,
            'longtitle' => (string)$category->longtitle,
            'menutitle' => (string)$category->menutitle,
            'alias' => (string)$category->alias,
            'url' => UrlProcessor::makeUrl((int)$category->id, '', '', 'full'),
            'published' => (int)$category->published,
            'deleted' => (int)$category->deleted,
            'active' => (int)$category->active,
            'hidemenu' => (int)$category->hidemenu,
            'menuindex' => (int)$category->menuindex,
            'level' => $level,
        ];
    }
}
