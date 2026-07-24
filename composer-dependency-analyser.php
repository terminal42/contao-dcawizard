<?php

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    // Ignore dependency error
    ->ignoreErrorsOnExtension('ext-zlib', [ErrorType::SHADOW_DEPENDENCY])
;
