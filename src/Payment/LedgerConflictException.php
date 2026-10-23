<?php namespace Seiger\sCommerce\Payment;

/**
 * A ledger write contradicts an existing entry with the same provider reference:
 * another order, kind, amount, currency or parent payment.
 *
 * @since 1.5.0
 */
final class LedgerConflictException extends \RuntimeException
{
}
