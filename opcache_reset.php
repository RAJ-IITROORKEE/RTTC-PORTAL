<?php
// One-time OPcache reset - DELETE THIS FILE after use
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo 'OPcache cleared. <a href="/mycode/PROJECTS/RTTC_2026/signup">Go to Signup</a>';
} else {
    echo 'OPcache not enabled - no action needed. <a href="/mycode/PROJECTS/RTTC_2026/signup">Go to Signup</a>';
}
unlink(__FILE__);
