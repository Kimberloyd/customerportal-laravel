"use client";

type FooterLinkGroup = {
  heading: string;
  items: Array<{ name: string; url: string }>;
};

interface FooterSimpleProps {
  companyName?: string;
  logoSrc?: string;
  copyright?: string;
  description?: string;
  linkGroups?: FooterLinkGroup[];
  social?: {
    facebook?: string;
    twitter?: string;
    linkedin?: string;
    github?: string;
    discord?: string;
  };
}

export function FooterSimple({
  companyName = "Smoothui",
  logoSrc,
  description = "Build beautiful UIs, effortlessly.",
  linkGroups = [],
  social = {
    discord: "https://discord.com",
    github: "https://github.com",
    linkedin: "https://linkedin.com",
    twitter: "https://twitter.com",
  },
  copyright = "© 2024 Smoothui. All rights reserved.",
}: FooterSimpleProps) {
  return (
    <footer className="border-border border-t bg-background">
      <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-5">
          {/* Company Info */}
          <div className="lg:col-span-2">
            {logoSrc ? (
              /* biome-ignore lint/performance/noImgElement: local static asset, no next/image in this project */
              <img src={logoSrc} alt={companyName} className="mb-4 h-20 w-auto" />
            ) : (
              <h3 className="mb-4 font-bold text-2xl text-foreground">
                {companyName}
              </h3>
            )}
            {description ? (
              <p className="mb-6 max-w-md font-display text-xl italic tracking-wide text-foreground/80">
                {description}
              </p>
            ) : null}

            {/* Social Links */}
            <div className="flex gap-4">
              {social.facebook ? (
                <a
                  className="text-foreground/60 transition-colors hover:text-primary"
                  href={social.facebook}
                  rel="noopener noreferrer"
                  target="_blank"
                >
                  <svg
                    aria-hidden="true"
                    className="h-8 w-8"
                    viewBox="0 0 666.667 666.667"
                  >
                    <defs>
                      <clipPath id="footer-facebook-icon" clipPathUnits="userSpaceOnUse">
                        <path d="M0 700h700V0H0Z" />
                      </clipPath>
                    </defs>
                    <g
                      clipPath="url(#footer-facebook-icon)"
                      transform="matrix(1.33333 0 0 -1.33333 -133.333 800)"
                    >
                      <path
                        d="M0 0c0 138.071-111.929 250-250 250S-500 138.071-500 0c0-117.245 80.715-215.622 189.606-242.638v166.242h-51.552V0h51.552v32.919c0 85.092 38.508 124.532 122.048 124.532 15.838 0 43.167-3.105 54.347-6.211V81.986c-5.901.621-16.149.932-28.882.932-40.993 0-56.832-15.528-56.832-55.9V0h81.659l-14.028-76.396h-67.631v-171.773C-95.927-233.218 0-127.818 0 0"
                        style={{ fill: '#0866ff', fillOpacity: '1', fillRule: 'nonzero', stroke: 'none' }}
                        transform="translate(600 350)"
                      />
                      <path
                        d="m0 0 14.029 76.396H-67.63v27.019c0 40.372 15.838 55.899 56.831 55.899 12.733 0 22.981-.31 28.882-.931v69.253c-11.18 3.106-38.509 6.212-54.347 6.212-83.539 0-122.048-39.441-122.048-124.533V76.396h-51.552V0h51.552v-166.242a250.559 250.559 0 0 1 60.394-7.362c10.254 0 20.358.632 30.288 1.831V0Z"
                        style={{ fill: '#fff', fillOpacity: '1', fillRule: 'nonzero', stroke: 'none' }}
                        transform="translate(447.918 273.604)"
                      />
                    </g>
                  </svg>
                  <span className="sr-only">Facebook</span>
                </a>
              ) : null}
              {social.twitter ? (
                <a
                  className="text-foreground/60 transition-colors hover:text-primary"
                  href={social.twitter}
                  rel="noopener noreferrer"
                  target="_blank"
                >
                  <svg
                    aria-hidden="true"
                    className="h-8 w-8"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z" />
                  </svg>
                  <span className="sr-only">Twitter</span>
                </a>
              ) : null}
              {social.linkedin ? (
                <a
                  className="text-foreground/60 transition-colors hover:text-primary"
                  href={social.linkedin}
                  rel="noopener noreferrer"
                  target="_blank"
                >
                  <svg
                    aria-hidden="true"
                    className="h-8 w-8"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" />
                  </svg>
                  <span className="sr-only">LinkedIn</span>
                </a>
              ) : null}
              {social.github ? (
                <a
                  className="text-foreground/60 transition-colors hover:text-primary"
                  href={social.github}
                  rel="noopener noreferrer"
                  target="_blank"
                >
                  <svg
                    aria-hidden="true"
                    className="h-8 w-8"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z" />
                  </svg>
                  <span className="sr-only">GitHub</span>
                </a>
              ) : null}
              {social.discord ? (
                <a
                  className="text-foreground/60 transition-colors hover:text-primary"
                  href={social.discord}
                  rel="noopener noreferrer"
                  target="_blank"
                >
                  <svg
                    aria-hidden="true"
                    className="h-8 w-8"
                    fill="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z" />
                  </svg>
                  <span className="sr-only">Discord</span>
                </a>
              ) : null}
            </div>
          </div>

          {/* Links */}
          {linkGroups.length > 0 ? (
            <div className="grid grid-cols-1 gap-8 sm:grid-cols-3 lg:col-span-3">
              {linkGroups.map((group) => (
                <div key={group.heading}>
                  <h4 className="mb-4 font-semibold text-foreground text-sm uppercase tracking-wide">
                    {group.heading}
                  </h4>
                  <ul className="space-y-3">
                    {group.items.map((link) => (
                      <li key={link.name}>
                        <a
                          className="text-foreground/70 text-sm underline-offset-4 transition-colors hover:text-primary hover:underline"
                          href={link.url}
                        >
                          {link.name}
                        </a>
                      </li>
                    ))}
                  </ul>
                </div>
              ))}
            </div>
          ) : null}
        </div>

        {/* Copyright */}
        <div className="pt-8 text-center">
          <p className="text-foreground/60 text-sm">{copyright}</p>
        </div>
      </div>
    </footer>
  );
}

export default FooterSimple;
