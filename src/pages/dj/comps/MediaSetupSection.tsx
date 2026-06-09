import React from "react";

type Props = {
    user: {
        dj_profile: {
            dj_name: string;
            handle: string;
        };
    };
};

const MediaSetupSection = ({
    user,
}: Props) => {
  return (
    <section className="border-b border-[#1a1a1a] px-4 py-12 lg:px-8">
      <div className="container mx-auto max-w-6xl">
        <p
          className="mb-3 text-xs font-bold uppercase tracking-[0.25em] text-primary"
          style={{ fontFamily: "var(--font-heading)" }}
        >
          Media Setup
        </p>
        <h1
          className="max-w-4xl text-white uppercase leading-none"
          style={{
            fontFamily: "var(--font-heading)",
            fontSize: "clamp(3.75rem, 9vw, 7rem)",
          }}
        >
          Get Started With Your Portfolio
        </h1>
        <p className="mt-5 max-w-2xl text-base leading-7 text-[#aaaaaa]">
          Create your free media storage workspace for {user.dj_profile.dj_name}
          . We will use your DJ handle to create a public folder like{" "}
          <span className="text-white">
            media/accounts/{user.dj_profile.handle}
          </span>
          .
        </p>
      </div>
    </section>
  );
};

export default MediaSetupSection;
