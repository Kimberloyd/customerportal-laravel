# nginx serves the compiled public/ directory directly and only proxies
# .php requests to the app (PHP-FPM) container -- it needs its own copy
# of the built assets, since it's a separate container/filesystem from
# app. Mirrors the sibling Flask app's nginx/Dockerfile, which copies
# static/ into the nginx image the same way.
FROM node:20-slim@sha256:2cf067cfed83d5ea958367df9f966191a942351a2df77d6f0193e162b5febfc0 AS assets
WORKDIR /app
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY}
ENV VITE_REVERB_HOST=${VITE_REVERB_HOST}
ENV VITE_REVERB_PORT=${VITE_REVERB_PORT}
ENV VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}
COPY package.json package-lock.json .npmrc ./
RUN npm ci --ignore-scripts
COPY . .
RUN npm run build

FROM nginx:1.27-alpine@sha256:65645c7bb6a0661892a8b03b89d0743208a18dd2f3f17a54ef4b76fb8e2f2a10

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public/ /app/public/
COPY --from=assets /app/public/build /app/public/build
