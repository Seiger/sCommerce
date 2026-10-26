<?php namespace Seiger\sCommerce\Shipping;

/**
 * A shipment write contradicts stored shipments: the external reference belongs to another
 * order, or the shipped quantities exceed or overlap the quantities of the order.
 *
 * @since 1.5.0
 */
final class ShipmentConflictException extends \RuntimeException
{
}
