<?php

namespace App\Support\Import;

/** Internal signal used to roll back a preview import transaction. */
class DryRunComplete extends \RuntimeException {}
