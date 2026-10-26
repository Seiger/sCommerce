<?php namespace Seiger\sCommerce\Services;

/**
 * An order cannot commit its stock because a product has less inventory than the order needs.
 * Nothing is decremented; the stock reconciliation retries the order later.
 *
 * @since 1.5.0
 */
final class StockShortageException extends \RuntimeException
{
}
