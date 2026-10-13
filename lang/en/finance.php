<?php

return [
    'monthly_for' => 'Monthly contribution — :period',
    'reason_required' => 'A reason is required and will be kept in the audit trail.',
    'already_reversed' => 'This transaction has already been reversed.',
    'amount_positive' => 'The amount must be greater than zero.',
    'cannot_waive' => 'This obligation cannot be waived in its current state.',
    'no_self_verify' => 'You cannot verify your own payment. Another finance officer must verify it.',
    'no_self_approve' => 'You cannot approve a request you submitted yourself.',
    'submission_not_pending' => 'This payment submission has already been handled.',
    'duplicate_reference' => 'Transaction reference ":ref" is already verified on another payment.',
    'dup_verified' => 'This reference is already verified (:date).',
    'dup_submission' => 'This reference also appears on another submission (:date).',
    'expense_not_pending' => 'This request has already been decided.',
    'expense_not_approved' => 'The expense must be approved before it can be paid.',
    'insufficient' => 'Not enough :fund money in this account (available: :available).',
    'invalid_transfer' => 'This transfer is not valid.',
    'rule_not_confirmed' => 'This rule has not been confirmed by leadership, so the system cannot apply it.',
];
