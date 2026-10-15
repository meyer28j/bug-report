// Site-wide easter egg: squashing the ant logo beside the title releases a
// few ants that sit idle for long stretches, now and then wander a short way,
// skitter away from a click/tap near them, and get squashed by a click/tap on
// them. They crawl beneath all page content, so clicks are matched to ants by
// distance rather than by hit-testing the ant element, and still go through
// to whatever was clicked. Skipped entirely for visitors who prefer reduced
// motion (the logo stays a static decoration).
(function () {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const logo = document.querySelector('.site-logo');
    if (!logo) return;
    logo.classList.add('active');

    const COUNT = 3;
    const SIZE = 28;          // px, matches .crawl-bug width/height
    const WALK_SPEED = 0.35;  // px per 60fps frame
    const FLEE_SPEED = 4;
    const SQUASH_RADIUS = 18; // px from bug center to click
    const FLEE_RADIUS = 110;
    const IDLE_MS = [5000, 15000];
    const WALK_MS = [1000, 3000];
    const FLEE_MS = 700;
    const RESPAWN_MS = 4000;

    // The logo's ant (helpers.php ANT_SVG) has its head pointing up, -90° in
    // screen coordinates.
    const HEAD_ANGLE = -0.5 * Math.PI;
    const ANT_SVG = logo.innerHTML;

    const bugs = [];

    function between([min, max]) {
        return min + Math.random() * (max - min);
    }

    // With a point, the ant bursts out from there; otherwise it fades in
    // somewhere random, idle.
    function spawn(from) {
        const el = document.createElement('div');
        el.className = 'crawl-bug';
        el.innerHTML = ANT_SVG;
        el.setAttribute('aria-hidden', 'true');
        document.body.appendChild(el);

        const bug = {
            el,
            x: Math.random() * (innerWidth - SIZE),
            y: Math.random() * (innerHeight - SIZE),
            angle: Math.random() * Math.PI * 2,
            speed: 0,
            walking: false,
            timer: between([0, IDLE_MS[1]]),  // staggered so they don't all move at once
            squashed: false,
        };
        if (from) {
            bug.x = from.x - SIZE / 2;
            bug.y = from.y - SIZE / 2;
            bug.speed = FLEE_SPEED;
            bug.walking = true;
            bug.timer = FLEE_MS * 2;
        }
        place(bug);
        bugs.push(bug);
    }

    function squash(bug) {
        bug.squashed = true;
        bug.el.classList.add('squashed');
        // Flatten in the ant's own frame, after its translate, so it stays put.
        bug.el.style.transform += ' scale(1.5, 0.3)';
        setTimeout(() => {
            bug.el.remove();
            bugs.splice(bugs.indexOf(bug), 1);
            spawn();
        }, RESPAWN_MS);
    }

    // A click/tap on a bug squashes it; one near a bug startles it away.
    document.addEventListener('pointerdown', (e) => {
        for (const bug of bugs) {
            if (bug.squashed) continue;
            const dx = bug.x + SIZE / 2 - e.clientX;
            const dy = bug.y + SIZE / 2 - e.clientY;
            const dist = Math.hypot(dx, dy);
            if (dist < SQUASH_RADIUS) {
                squash(bug);
            } else if (dist < FLEE_RADIUS) {
                bug.angle = Math.atan2(dy, dx) + (Math.random() - 0.5) * 0.8;
                bug.speed = FLEE_SPEED;
                bug.walking = true;
                bug.timer = FLEE_MS;
            }
        }
    });

    let last = performance.now();
    function tick(now) {
        const ms = Math.min(now - last, 50);
        // Normalize to 60fps so high-refresh monitors don't run bugs faster.
        const dt = ms / (1000 / 60);
        last = now;
        const maxX = innerWidth - SIZE;
        const maxY = innerHeight - SIZE;

        for (const bug of bugs) {
            if (bug.squashed) continue;

            bug.timer -= ms;
            if (bug.timer <= 0) {
                bug.walking = !bug.walking;
                if (bug.walking) {
                    bug.angle += (Math.random() - 0.5) * Math.PI;  // turn up to 90° either way
                    bug.speed = WALK_SPEED;
                    bug.timer = between(WALK_MS);
                } else {
                    bug.timer = between(IDLE_MS);
                }
            }
            if (!bug.walking) continue;

            bug.angle += (Math.random() - 0.5) * 0.15 * dt;
            bug.speed += (WALK_SPEED - bug.speed) * 0.04 * dt;
            bug.x += Math.cos(bug.angle) * bug.speed * dt;
            bug.y += Math.sin(bug.angle) * bug.speed * dt;

            // Bounce off viewport edges.
            if (bug.x < 0 || bug.x > maxX) {
                bug.angle = Math.PI - bug.angle;
                bug.x = Math.max(0, Math.min(bug.x, maxX));
            }
            if (bug.y < 0 || bug.y > maxY) {
                bug.angle = -bug.angle;
                bug.y = Math.max(0, Math.min(bug.y, maxY));
            }

            place(bug);
        }
        requestAnimationFrame(tick);
    }

    // Rotate so the head points along the heading.
    function place(bug) {
        bug.el.style.transform = `translate(${bug.x}px, ${bug.y}px) rotate(${bug.angle - HEAD_ANGLE}rad)`;
    }

    // Squashing the logo (once per page load) lets the ants out.
    logo.addEventListener('click', () => {
        if (logo.classList.contains('squashed')) return;
        logo.classList.add('squashed');
        const r = logo.getBoundingClientRect();
        const from = { x: r.left + r.width / 2, y: r.top + r.height / 2 };
        for (let i = 0; i < COUNT; i++) spawn(from);
        last = performance.now();
        requestAnimationFrame(tick);
    });
})();
