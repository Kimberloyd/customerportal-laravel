# nginx serves the compiled public/ directory directly and only proxies
# .php requests to the app (PHP-FPM) container -- it needs its own copy
# of the built assets, since it's a separate container/filesystem from
# app. Mirrors the sibling Flask app's nginx/Dockerfile, which copies
# static/ into the nginx image the same way.
FROM node:20-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM nginx:1.27-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public/ /app/public/
COPY --from=assets /app/public/build /app/public/build
