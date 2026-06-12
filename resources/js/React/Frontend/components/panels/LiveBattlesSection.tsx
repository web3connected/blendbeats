import { motion } from "motion/react";
import { fadeUp } from "@/config/animations";
import { siteMedia } from "@/lib/site-media";

const features = [
    {
        label: "Community",
        title: "DJ Lounge",
        description:
            "Build your presence, connect with other DJs, share updates, showcase mixes, and grow your audience through the BlendBeats community.",
        image: "images/pages/home/live-battles/dj-lounge.avif",
        alt: "DJ Lounge",
        href: "/dj-lounge",
    },
    {
        label: "Directory",
        title: "DJ Hub",
        description:
            "Browse DJ profiles, portfolios, mixes, genres, and featured artists. Discover talent and connect with DJs from around the world.",
        image: "images/pages/home/live-battles/dj-hub.jpg",
        alt: "DJ Hub",
        href: "/djs",
    },
    {
        label: "Competition",
        title: "Battles",
        description:
            "Enter monthly competitions, challenge other DJs, earn votes, climb the rankings, and compete for prizes and recognition.",
        image: "images/pages/home/live-battles/dj-battles.jpg",
        alt: "DJ Battles",
        href: "/battles",
    },
];

const LiveBattlesSection = () => {
    return (
        <section className="border-t border-[#1a1a1a] bg-[#0a0a0a] py-16 lg:py-24">
            {" "}
            <div className="container mx-auto px-4 lg:px-8">
                {" "}
                <div className="mb-12 text-center">
                    <span
                        className="mb-4 inline-block text-xs font-bold uppercase tracking-[0.3em] text-primary"
                        style={{ fontFamily: "var(--font-heading)" }}
                    >
                        BlendBeats Platform{" "}
                    </span>
                    <h2
                        className="mb-4 text-4xl uppercase text-white md:text-5xl"
                        style={{ fontFamily: "var(--font-heading)" }}
                    >
                        Everything A DJ Needs
                    </h2>
                    <p className="mx-auto max-w-3xl text-gray-400">
                        BlendBeats combines community, discovery, and
                        competition into one platform designed specifically for
                        DJs.
                    </p>
                </div>
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {features.map((feature, i) => (
                        <motion.article
                            key={feature.title}
                            custom={i}
                            initial="hidden"
                            whileInView="visible"
                            viewport={{ once: true }}
                            variants={fadeUp}
                            className="group relative min-h-[420px] overflow-hidden border border-[#222] bg-[#111]"
                        >
                            <img
                                src={siteMedia(feature.image)}
                                alt={feature.alt}
                                loading="lazy"
                                className="absolute inset-0 h-full w-full object-cover opacity-60 transition-all duration-500 group-hover:scale-105 group-hover:opacity-80"
                            />

                            <div className="absolute inset-0 bg-gradient-to-t from-black via-black/50 to-transparent" />

                            <div className="absolute inset-x-0 bottom-0 p-8">
                                <span
                                    className="mb-3 block text-xs font-bold uppercase tracking-[0.25em] text-primary"
                                    style={{
                                        fontFamily: "var(--font-heading)",
                                    }}
                                >
                                    {feature.label}
                                </span>

                                <h3
                                    className="mb-4 text-4xl uppercase text-white"
                                    style={{
                                        fontFamily: "var(--font-heading)",
                                    }}
                                >
                                    {feature.title}
                                </h3>

                                <p className="mb-6 text-sm leading-relaxed text-gray-300">
                                    {feature.description}
                                </p>

                                <a
                                    href={feature.href}
                                    className="inline-flex items-center border border-primary px-5 py-3 text-sm font-bold uppercase tracking-wider text-white transition-all duration-300 hover:bg-primary"
                                >
                                    Explore
                                </a>
                            </div>
                        </motion.article>
                    ))}
                </div>
            </div>
        </section>
    );
};

export default LiveBattlesSection;
