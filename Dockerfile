ARG gopath_default=/tmp/build-golang

FROM bitnami/minideb-extras:jessie-buildpack as BUILD

ARG gopath_default
ENV GOPATH=$gopath_default
ENV PATH=$GOPATH/bin:/opt/bitnami/go/bin:$PATH
WORKDIR $GOPATH/src/github.com/didi/rdebug
COPY . $GOPATH/src/github.com/didi/rdebug

RUN mkdir -p $GOPATH/bin && bitnami-pkg install go-1.8.3-0 --checksum 557d43c4099bd852c702094b6789293aed678b253b80c34c764010a9449ff136
RUN curl https://glide.sh/get | sh && bitnami-pkg install nginx-1.14.0-0
RUN cd koala-libc && sh build.sh \
    && cd ../koala && sh build.sh vendor && sh build.sh && sh build.sh recorder

FROM bitnami/php-fpm:7.1-debian-8 as FPM

ARG gopath_default
ENV PATH=/opt/bitnami/nginx/sbin:/opt/bitnami/php/bin:/opt/bitnami/php/sbin:$PATH
WORKDIR /usr/local/var/koala
COPY ./php/midi /usr/local/var/midi
COPY --from=BUILD /opt/bitnami/nginx/sbin /opt/bitnami/nginx/sbin
COPY --from=BUILD /bitnami/nginx/conf /opt/bitnami/nginx/conf
COPY --from=BUILD $gopath_default/src/github.com/didi/rdebug/output/libs/*.so /usr/local/var/koala/
COPY --from=BUILD $gopath_default/src/github.com/didi/rdebug/output/libs/koala-replayer.so /usr/local/var/midi/res/replayer/
COPY ./composer.json /usr/local/var/midi/composer.json
COPY ./example/php/nginx.conf /opt/bitnami/nginx/conf
COPY ./example/php/index.php /usr/local/var/koala/index.php
COPY ./example/php/1548160113499755925-1158745 /usr/local/var/koala/1548160113499755925-1158745
COPY ./example/php/docker/start.sh /usr/local/var/koala/start.sh
COPY ./example/php/docker/supervisor.conf /usr/local/var/koala/supervisor.conf

RUN install_packages apt-utils git vim curl lsof procps ca-certificates sudo locales supervisor && \
    chmod 444 /usr/local/var/koala/*so && \
    addgroup nobody && \
    sed -i -e 's/\s*Defaults\s*secure_path\s*=/# Defaults secure_path=/' /etc/sudoers && \
        echo "nobody ALL=NOPASSWD: ALL" >> /etc/sudoers && \
    sed -i \
        -e "s/pm = ondemand/pm = static/g" \
        -e "s/^listen = 9000/listen = \/usr\/local\/var\/run\/php-fpm.sock/g" \
        -e "s/^;clear_env = no$/clear_env = no/" \
        /opt/bitnami/php/etc/php-fpm.d/www.conf && \
    sed -i \
        -e "s/user=daemon/user=nobody/g" \
        -e "s/^group=daemon/group=nobody/g" \
        -e "s/listen.owner=daemon/listen.owner=nobody/g" \
        -e "s/listen.group=daemon/listen.group=nobody/g" \
        /opt/bitnami/php/etc/common.conf

EXPOSE 9111

CMD ["./start.sh"]
