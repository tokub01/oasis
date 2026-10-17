<?php

namespace App\Support\Operations;

use RuntimeException;

/** A problem HealthCheck found itself; only these messages are shown, never those of drivers or libraries. */
final class HealthProblem extends RuntimeException {}
