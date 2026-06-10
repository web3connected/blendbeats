const app = document.querySelector("#app");

if (app) {
    app.innerHTML = `
        <main class="next-page">
            <section class="next-main" aria-labelledby="home-title">
                <div class="next-logo" aria-label="Next.js">Next.js</div>

                <ol class="next-steps">
                    <li>
                        Get started by editing
                        <code>resources/js/app.js</code>.
                    </li>
                    <li>Save and see your changes instantly.</li>
                </ol>

                <div class="next-actions">
                    <a
                        class="next-button next-button-primary"
                        href="https://vercel.com/new"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Deploy now
                    </a>
                    <a
                        class="next-button next-button-secondary"
                        href="https://nextjs.org/docs"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Read our docs
                    </a>
                </div>
            </section>

            <footer class="next-footer">
                <a href="https://nextjs.org/learn" target="_blank" rel="noopener noreferrer">Learn</a>
                <a href="https://vercel.com/templates" target="_blank" rel="noopener noreferrer">Examples</a>
                <a href="https://nextjs.org" target="_blank" rel="noopener noreferrer">Go to nextjs.org</a>
            </footer>
        </main>
    `;
}
