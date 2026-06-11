const app = document.querySelector("#app");

if (app) {
    app.innerHTML = `
        <main class="blendbeats-page">
            <section class="hero">
                <div class="container">
                    <span class="badge">🎧 Monthly DJ Battles</span>

                    <h1>BlendBeats</h1>

                    <p class="hero-text">
                        Compete against DJs from around the world using the
                        same monthly sample pack. Submit your mix, earn votes,
                        climb the rankings, and win prizes.
                    </p>

                    <div class="hero-actions">
                        <a href="/register" class="btn btn-primary">
                            Join Competition
                        </a>

                        <a href="/rankings" class="btn btn-secondary">
                            View Rankings
                        </a>
                    </div>
                </div>
            </section>

            <section class="features">
                <div class="container">
                    <h2>How It Works</h2>

                    <div class="feature-grid">
                        <div class="feature-card">
                            <h3>1. Download Samples</h3>
                            <p>
                                Every month receives a new sample pack
                                generated for the competition.
                            </p>
                        </div>

                        <div class="feature-card">
                            <h3>2. Create Your Mix</h3>
                            <p>
                                Build your best routine using the provided
                                sounds and your own creativity.
                            </p>
                        </div>

                        <div class="feature-card">
                            <h3>3. Submit Entry</h3>
                            <p>
                                Upload your performance before the competition
                                deadline.
                            </p>
                        </div>

                        <div class="feature-card">
                            <h3>4. Community Voting</h3>
                            <p>
                                Members vote for their favorite DJs and battle
                                performances.
                            </p>
                        </div>

                        <div class="feature-card">
                            <h3>5. Win Rewards</h3>
                            <p>
                                Earn rankings, badges, recognition, and prize
                                pool rewards.
                            </p>
                        </div>

                        <div class="feature-card">
                            <h3>6. Build Your Career</h3>
                            <p>
                                Showcase your profile, mixes, and achievements
                                to promoters and fans.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="competition">
                <div class="container">
                    <h2>Current Competition</h2>

                    <div class="competition-card">
                        <h3>Summer Battle 2026</h3>

                        <p>
                            Submit your mix using this month's official sample
                            pack and compete for the top spot.
                        </p>

                        <ul>
                            <li>🎵 3 Official Samples</li>
                            <li>🏆 Community Voting</li>
                            <li>🎖 DJ Rankings</li>
                            <li>💰 Prize Pool</li>
                        </ul>

                        <a href="/competition" class="btn btn-primary">
                            Enter Battle
                        </a>
                    </div>
                </div>
            </section>

            <footer class="footer">
                <div class="container">
                    <p>
                        © 2026 BlendBeats • DJ Battles • Competitions • Community
                    </p>
                </div>
            </footer>
        </main>
    `;
}
