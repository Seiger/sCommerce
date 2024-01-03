<?php namespace Seiger\sCommerce\Models;

use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

class sProduct extends Model
{
    /**
     * Availability constants
     */
    const AVAILABILITY_NOT_AVAILABLE = 0;
    const AVAILABILITY_IN_STOCK = 1;
    const AVAILABILITY_ON_ORDER = 2;

    /**
     * Type constants
     */
    const TYPE_SIMPLE = 0;
    const TYPE_OPTIONAL = 1;
    const TYPE_VARIABLE = 2;

    /**
     * Return list of availability codes and labels
     *
     * @return array
     */
    public static function listAvailability(): array
    {
        $list = [];
        $class = new ReflectionClass(__CLASS__);
        foreach ($class->getConstants() as $constant => $value) {
            if (str_starts_with($constant, 'AVAILABILITY_')) {
                $const = strtolower(str_replace('AVAILABILITY_', '', $constant));
                $list[$value] = __('sCommerce::global.'.$const);
            }
        }
        return $list;
    }

    /**
     * Return list of type codes and labels
     *
     * @return array
     */
    public static function listType(): array
    {
        $list = [];
        $class = new ReflectionClass(__CLASS__);
        foreach ($class->getConstants() as $constant => $value) {
            if (str_starts_with($constant, 'TYPE_')) {
                $const = strtolower($constant);
                $list[$value] = __('sCommerce::global.'.$const);
            }
        }
        return $list;
    }
}