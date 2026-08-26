# nginx serves the compiled public/ directory directly and only proxies
# .php requests to the app (PHP-FPM) container -- it needs its own copy
# of the built assets, since it's a separate container/filesystem from
# app. Mirrors the sibling Flask app's nginx/Dockerfile, which copies
# static/ into the nginx image the same way.
FROM node:20-slim AS assets
WORKDIR /app
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
ENV VITE_REVERB_APP_KEY=${VITE_REVERB_APP_KEY}
ENV VITE_REVERB_HOST=${VITE_REVERB_HOST}
ENV VITE_REVERB_PORT=${VITE_REVERB_PORT}
ENV VITE_REVERB_SCHEME=${VITE_REVERB_SCHEME}
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM nginx:1.27-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public/ /app/public/
COPY --from=assets /app/public/build /app/public/build
