<?php namespace Seiger\sCommerce\Services;

use EvolutionCMS\Core;
use Seiger\sCommerce\Models\sProduct;

/**
 * Apply manager role permissions to the gallery on the sCommerce product tab.
 *
 * @since 1.5.0
 */
final class ProductGalleryPolicy
{
    /**
     * Check module and action permissions before looking up the product.
     * sGallery checks the manager session and any file library permissions separately.
     *
     * @param string $action Gallery operation requested by the manager.
     * @param int $parent ID of the saved sCommerce product.
     * @param string $block Gallery block identifier.
     * @param Core $cms Evolution manager permission provider.
     * @return bool Whether to allow the requested operation on the product gallery.
     * @since 1.5.0
     */
    public static function allows(string $action, int $parent, string $block, Core $cms): bool
    {
        if ($parent < 1 || $block !== '1' || !$cms->hasPermission('exec_module', 'mgr')) {
            return false;
        }

        $allowed = match ($action) {
            'read' => $cms->hasPermission('view_document', 'mgr') || $cms->hasPermission('edit_document', 'mgr'),
            'upload', 'uploadDownload', 'register', 'addYoutube', 'reorder', 'translate', 'delete' =>
                $cms->hasPermission('edit_document', 'mgr') && $cms->hasPermission('save_document', 'mgr'),
            default => false,
        };
        if (!$allowed || ($action === 'delete' && !$cms->hasPermission('delete_document', 'mgr'))) {
            return false;
        }

        return sProduct::query()->withoutGlobalScope('translate')->whereKey($parent)->exists();
    }
}
