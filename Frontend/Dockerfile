FROM node:22-alpine@sha256:1314fb30ec37592d5845e5a160d522c0e680972aa534549940b8d73bde88481a AS build

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY . .

ARG VITE_API_URL=/api
ARG VITE_APP_BUILD_SHA=unknown
ENV VITE_API_URL=${VITE_API_URL}
ENV VITE_APP_BUILD_SHA=${VITE_APP_BUILD_SHA}

RUN npm run build

FROM nginx:alpine@sha256:b50897d5c7b1aa08067c3d90c5e8b3e47ec4e3c91c91446f67820228f7b41e34

ARG APP_BUILD_SHA=unknown
ENV APP_BUILD_SHA=${APP_BUILD_SHA}

RUN apk add --no-cache jq

COPY --from=build /app/dist /usr/share/nginx/html

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

COPY --chmod=0755 docker/40-generate-config.sh /docker-entrypoint.d/40-generate-config.sh

EXPOSE 80
