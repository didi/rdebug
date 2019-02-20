#ifdef KOALA_LIBC_FILE_HOOK
#ifndef _GNU_SOURCE
#define _GNU_SOURCE
#endif

#include <string.h>
#include <fcntl.h>
#include <unistd.h>
#include <stdarg.h>
#include <sys/stat.h>
#include "interpose.h"
#include "init.h"
#include "thread_id.h"
#include "_cgo_export.h"

#define FILE_HOOK_ENTER(name)

#ifndef __APPLE__
INTERPOSE(fopen64)(const char *filename, const char *opentype) {
    FILE_HOOK_ENTER(fopen64)
    if (is_go_initialized() != 1) {
        return real::fopen64(filename, opentype);
    }
    pid_t thread_id = get_thread_id();
    struct ch_span filename_span;
    filename_span.Ptr = filename;
    filename_span.Len = strlen(filename);
    struct ch_span opentype_span;
    opentype_span.Ptr = opentype;
    opentype_span.Len = strlen(opentype);
    struct ch_allocated_string redirect_to = on_fopening_file(thread_id, filename_span, opentype_span);
    if (redirect_to.Ptr != NULL) {
        FILE *file = real::fopen64(redirect_to.Ptr, opentype);
        if (file != NULL) {
            on_fopened_file(thread_id, fileno(file), filename_span, opentype_span);
        }
        free(redirect_to.Ptr);
        return file;
    }
    FILE *file = real::fopen64(filename, opentype);
    if (file != NULL) {
        on_fopened_file(thread_id, fileno(file), filename_span, opentype_span);
    }
    return file;
}

INTERPOSE(open64)(const char *filename, int flags, mode_t mode) {
    FILE_HOOK_ENTER(open64)
    if (is_go_initialized() != 1) {
        return real::open64(filename, flags, mode);
    }
    pid_t thread_id = get_thread_id();
    struct ch_span filename_span;
    filename_span.Ptr = filename;
    filename_span.Len = strlen(filename);
    struct ch_allocated_string redirect_to = on_opening_file(thread_id, filename_span, flags, mode);
    if (redirect_to.Ptr != NULL) {
        int file = real::open64(redirect_to.Ptr, flags, mode);
        if (file != -1) {
            on_opened_file(thread_id, file, filename_span, flags, mode);
        }
        free(redirect_to.Ptr);
        return file;
    }
    int file = real::open64(filename, flags, mode);
    if (file != -1) {
        on_opened_file(thread_id, file, filename_span, flags, mode);
    }
    return file;
}
#endif // __APPLE__

INTERPOSE(fopen)(const char *filename, const char *opentype) {
    FILE_HOOK_ENTER(fopen)
    if (is_go_initialized() != 1) {
        return real::fopen(filename, opentype);
    }
    pid_t thread_id = get_thread_id();
    struct ch_span filename_span;
    filename_span.Ptr = filename;
    filename_span.Len = strlen(filename);
    struct ch_span opentype_span;
    opentype_span.Ptr = opentype;
    opentype_span.Len = strlen(opentype);
    struct ch_allocated_string redirect_to = on_fopening_file(thread_id, filename_span, opentype_span);
    if (redirect_to.Ptr != NULL) {
        auto file = real::fopen(redirect_to.Ptr, opentype);
        if (file != NULL) {
            on_fopened_file(thread_id, fileno(file), filename_span, opentype_span);
        }
        free(redirect_to.Ptr);
        return file;
    }
    auto file = real::fopen(filename, opentype);
    if (file != NULL) {
        on_fopened_file(thread_id, fileno(file), filename_span, opentype_span);
    }
    return file;
}

INTERPOSE(open)(const char *filename, int flags, ...) {
    FILE_HOOK_ENTER(open)
    if(flags & O_CREAT){
        va_list vl;
        va_start(vl,flags);
        mode_t mode = va_arg(vl,int);
        va_end(vl);
        if (is_go_initialized() != 1) {
            return real::open(filename, flags, mode);
        }
        pid_t thread_id = get_thread_id();
        struct ch_span filename_span;
        filename_span.Ptr = filename;
        filename_span.Len = strlen(filename);
        struct ch_allocated_string redirect_to = on_opening_file(thread_id, filename_span, flags, mode);
        if (redirect_to.Ptr != NULL) {
            int file = real::open(redirect_to.Ptr, flags, mode);
            if (file != -1) {
                on_opened_file(thread_id, file, filename_span, flags, mode);
            }
            free(redirect_to.Ptr);
            return file;
        }
        int file = real::open(filename, flags, mode);
        if (file != -1) {
            on_opened_file(thread_id, file, filename_span, flags, mode);
        }
        return file;
    } else {
        if (is_go_initialized() != 1) {
            return real::open(filename, flags);
        }
        pid_t thread_id = get_thread_id();
        struct ch_span filename_span;
        filename_span.Ptr = filename;
        filename_span.Len = strlen(filename);
        struct ch_allocated_string redirect_to = on_opening_file(thread_id, filename_span, flags, 0);
        if (redirect_to.Ptr != NULL) {
            int file = real::open(redirect_to.Ptr, flags);
            if (file != -1) {
                on_opened_file(thread_id, file, filename_span, flags, 0);
            }
            free(redirect_to.Ptr);
            return file;
        }
        int file = real::open(filename, flags);
        if (file != -1) {
            on_opened_file(thread_id, file, filename_span, flags, 0);
        }
        return file;
    }
}

INTERPOSE(read)(int fileFD, void *buffer, size_t size) {
    FILE_HOOK_ENTER(read)
    if (is_go_initialized() != 1) {
        return real::read(fileFD, buffer, size);
    }
    ssize_t read_size = real::read(fileFD, buffer, size);
    if (read_size >= 0) {
        struct stat statbuf;
        fstat(fileFD, &statbuf);
        if (S_ISSOCK(statbuf.st_mode)) {
            pid_t thread_id = get_thread_id();
            struct ch_span span;
            span.Ptr = buffer;
            span.Len = read_size;
            on_recv(thread_id, fileFD, span, 0);
        }
    }
    return read_size;
}

INTERPOSE(write)(int fileFD, const void *buffer, size_t size) {
    FILE_HOOK_ENTER(write)
    if (is_go_initialized() != 1) {
        return real::write(fileFD, buffer, size);
    }
    ssize_t written_size = real::write(fileFD, buffer, size);
    if (written_size >= 0) {
        pid_t thread_id = get_thread_id();
        struct ch_span span;
        span.Ptr = buffer;
        span.Len = written_size;
        struct stat statbuf;
        fstat(fileFD, &statbuf);
        if (S_ISSOCK(statbuf.st_mode)) {
            on_send(thread_id, fileFD, span, 0, 0);
        } else {
            on_write(thread_id, fileFD, span);
        }
    }
    return written_size;
}
#endif // KOALA_LIBC_FILE_HOOK
