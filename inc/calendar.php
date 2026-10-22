<?php
/** Month calendar card. Needs $cal, $calMap, $calFirst, $calDays, $calLead, $prevCal, $nextCal, $today. Loaded in place via ?partial=cal. */
?>
<div class="card h-100"><div class="card-header d-flex justify-content-between align-items-center"><span><?= e(date('F Y', $calFirst)) ?><?php if ($cal !== date('Y-m')): ?> <a class="small ms-1" href="?cal=<?= date('Y-m') ?>" data-cal="<?= date('Y-m') ?>">today</a><?php endif; ?></span>
        <span><a class="btn btn-sm btn-light" href="?cal=<?= $prevCal ?>" data-cal="<?= $prevCal ?>" aria-label="Previous month"><i class="bi bi-chevron-left"></i></a>
            <a class="btn btn-sm btn-light" href="?cal=<?= $nextCal ?>" data-cal="<?= $nextCal ?>" aria-label="Next month"><i class="bi bi-chevron-right"></i></a></span></div>
        <div class="card-body">
            <div class="cal">
                <?php foreach (['M', 'T', 'W', 'T', 'F', 'S', 'S'] as $d): ?><div class="cal-h"><?= $d ?></div><?php endforeach; ?>
                <?php for ($i = 0; $i < $calLead; $i++): ?><div></div><?php endfor; ?>
                <?php for ($d = 1; $d <= $calDays; $d++):
                    $ds = sprintf('%s-%02d', $cal, $d); $has = isset($calMap[$ds]); $future = $ds > $today; $isToday = $ds === $today;
                    $cls = 'cal-d' . ($has ? ' has' : ($future ? ' future' : ' miss')) . ($isToday ? ' today' : '');
                    $tip = $has ? fmt_date($ds, 'D j M') . ': received ' . qty($calMap[$ds]['received_total']) . ', closing ' . qty($calMap[$ds]['closing']) : ($future ? fmt_date($ds, 'D j M') : fmt_date($ds, 'D j M') . ': no record'); ?>
                    <?php if (!$future && (can_record() || $has)): ?><a class="<?= $cls ?>" href="<?= admin_url('record?date=' . $ds) ?>" title="<?= e($tip) ?>"><?= $d ?></a>
                    <?php else: ?><span class="<?= $cls ?>" title="<?= e($tip) ?>"><?= $d ?></span><?php endif; ?>
                <?php endfor; ?>
            </div>
            <div class="small text-body-secondary mt-3"><i class="sw sw-recv"></i>Recorded <i class="sw ms-3" style="background:var(--line)"></i>Missing</div>
        </div></div>
