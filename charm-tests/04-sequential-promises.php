<?php
use Moebius\Promise;
use Moebius\Deferred;

/**
 * Success then success
 */
foreach ([
    [
        [true, 'A1'],
        [true, 'B1'],
        [true, 'C1'],
    ],
    [
        [true, 'A2'],
        [true, 'B2'],
        [false, 'c2'],
    ],
    [
        [true, 'A3'],
        [false, 'b3'],
        [true, 'C3'],
    ],
    [
        [true, 'A4'],
        [false, 'b4'],
        [false, 'c4'],
    ],
    [
        [false, 'a5'],
        [true, 'B5'],
        [true, 'C5'],
    ],
    [
        [false, 'a6'],
        [true, 'B6'],
        [false, 'c6'],
    ],
    [
        [false, 'a7'],
        [false, 'b7'],
        [true, 'C7'],
    ],
    [
        [false, 'a8'],
        [false, 'b8'],
        [false, 'c8'],
    ]
] as $scenario) {
    $p = new Deferred();
    $trace = '';
    $p->then(
        function($value) use ($scenario, &$trace) {
            if ($value instanceof \Throwable) {
                echo "FULFILL WITH EXCEPTION\n"; debug_print_backtrace(); die();
                $trace .= $value->getMessage();
            } else {
                $trace .= $value;
            }
            if ($scenario[1][0]) {
                echo "    First fulfilled with value=".json_encode($value)." then fulfilling with value=".json_encode($scenario[1][1])."\n";
                return $scenario[1][1];
            } else {
                echo "    First fulfilled with value=".json_encode($value)." then rejecting with value=".json_encode($scenario[1][1])."\n";
                throw new \Exception($scenario[1][1]);
            }
        }, function($value) use ($scenario, &$trace) {
            if ($value instanceof \Throwable) {
                $trace .= $value->getMessage();
            } else {
                $trace .= $value;
            }
            if ($scenario[1][0]) {
                echo "    First rejected with value=".json_encode($value->getMessage())." then fulfilling with value=".json_encode($scenario[1][1])."\n";
                return $scenario[1][1];
            } else {
                echo "    First rejected with value=".json_encode($value->getMessage())." then rejecting with value=".json_encode($scenario[1][1])."\n";
                throw new \Exception($scenario[1][1]);
            }
        }
    )->then(
        function($value) use ($scenario, &$trace) {
            if ($value instanceof \Throwable) {
                echo "FULFILL WITH EXCEPTION\n"; debug_print_backtrace(); die();
                $trace .= $value->getMessage();
            } else {
                $trace .= $value;
            }
            if ($scenario[2][0]) {
                echo "        Second fulfilled with value=".json_encode($value)." then fulfilling with value=".json_encode($scenario[2][1])."\n";
                return $scenario[2][1];
            } else {
                echo "        Second fulfilled with value=".json_encode($value)." then rejecting with value=".json_encode($scenario[2][1])."\n";
                throw new \Exception($scenario[2][1]);
            }
        }, function($value) use ($scenario, &$trace) {
            if ($value instanceof \Throwable) {
                $trace .= $value->getMessage();
            } else {
                $trace .= $value;
            }
            if ($scenario[2][0]) {
                echo "        Second rejected with value=".json_encode($value->getMessage())." then fulfilling with value=".json_encode($scenario[2][1])."\n";
                return $scenario[2][1];
            } else {
                echo "        Second rejected with value=".json_encode($value->getMessage())." then rejecting with value=".json_encode($scenario[2][1])."\n";
                throw new \Exception($scenario[2][1]);
            }
        }
    )->then(
        function($value) use ($scenario, &$trace) {
            if ($value instanceof \Throwable) {
                echo "FULFILL WITH EXCEPTION\n"; debug_print_backtrace(); die();
                $trace .= $value->getMessage();
            } else {
                $trace .= $value;
            }
            echo "            Finally successful\n";
        }, function($value) use ($scenario, &$trace) {
            if ($value instanceof \Throwable) {
                $trace .= $value->getMessage();
            } else {
                $trace .= $value;
            }
            echo "            Finally rejected\n";
        }
    );

    if ($scenario[0][0]) {
        echo "Fulfilling initial promise (value=".json_encode($scenario[0][1]).")\n";
        $p->fulfill($scenario[0][1]);
    } else {
        echo "Rejecting initial promise (value=".json_encode($scenario[0][1]).")\n";
        $p->reject(new \Exception($scenario[0][1]));
    }

    echo "RESULT: $trace\n";
    echo "---\n";
}
