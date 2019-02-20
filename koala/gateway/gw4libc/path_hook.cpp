#ifdef KOALA_LIBC_PATH_HOOK
#ifndef _GNU_SOURCE
#define _GNU_SOURCE
#endif

#include <dlfcn.h>
#include <stddef.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/syscall.h>
#include <dirent.h>
#include <sys/stat.h>
#include <unistd.h>
#include <sys/xattr.h>
#ifndef __APPLE__
#include <sys/vfs.h>
#include <sys/statvfs.h>
#endif
#include <utime.h>
#include <sys/time.h>
#include "interpose.h"
#include "init.h"
#include "thread_id.h"
#include "_cgo_export.h"

#define PATH_HOOK_ENTER(name) \
    char *redirect_to = try_redirect_path(path); \
    if (redirect_to != NULL) { \
        path = redirect_to; \
    }
#define PATH_HOOK_EXIT \
    if (redirect_to != NULL) { \
        free(redirect_to); \
    } \
    return result;

#define DOUBLE_PATH_HOOK_ENTER(name) \
    char *redirect_to1 = try_redirect_path(path1); \
    if (redirect_to1 != NULL) { \
        path1 = redirect_to1; \
    } \
    char *redirect_to2 = try_redirect_path(path2); \
    if (redirect_to2 != NULL) { \
        path2 = redirect_to2; \
    }

#define DOUBLE_PATH_HOOK_EXIT \
    if (redirect_to1 != NULL) { \
        free(redirect_to1); \
    } \
    if (redirect_to2 != NULL) { \
        free(redirect_to2); \
    } \
    return result;

char *try_redirect_path(const char *path) {
    if (is_go_initialized() != 1) {
        return NULL;
    }
    pid_t thread_id = get_thread_id();
    struct ch_span path_span;
    path_span.Ptr = path;
    path_span.Len = strlen(path);
    struct ch_allocated_string redirect_to = redirect_path(thread_id, path_span);
    return redirect_to.Ptr;
}

#ifdef __APPLE__
INTERPOSE(getxattr)(const char *path, const char *name, void *value, size_t size, u_int32_t position, int options){
    PATH_HOOK_ENTER(getxattr)
    auto result = real::getxattr(path, name, value, size, position, options);
    PATH_HOOK_EXIT
}
//INTERPOSE(setxattr)(const char *path, const char *name, void *value, size_t size, u_int32_t position, int options){
//    PATH_HOOK_ENTER
//    auto result = real::setxattr(path, name, value, size, position, options);
//    PATH_HOOK_EXIT
//}
#else // __APPLE__
INTERPOSE(getxattr)(const char *path, const char *name, void *value, size_t size){
    PATH_HOOK_ENTER(getxattr)
    auto result = real::getxattr(path, name, value, size);
    PATH_HOOK_EXIT
}
INTERPOSE(setxattr)(const char *path, const char *name, const void *value, size_t size, int flags){
    PATH_HOOK_ENTER(setxattr)
    auto result = real::setxattr(path, name, value, size, flags);
    PATH_HOOK_EXIT
}
INTERPOSE(__xstat)(int ver, const char *path, struct stat *buf) {
    PATH_HOOK_ENTER(__xstat)
    auto result = real::__xstat(ver, path, buf);
    PATH_HOOK_EXIT
}
INTERPOSE(__xstat64)(int ver, const char *path, struct stat64 *buf) {
    PATH_HOOK_ENTER(__xstat64)
    auto result = real::__xstat64(ver, path, buf);
    PATH_HOOK_EXIT
}
INTERPOSE(__lxstat)(int ver, const char *path, struct stat *buf) {
    PATH_HOOK_ENTER(__lxstat)
    auto result = real::__lxstat(ver, path, buf);
    PATH_HOOK_EXIT
}
INTERPOSE(statfs)(const char *path, struct statfs *buf){
    PATH_HOOK_ENTER(statfs)
    auto result = real::statfs(path, buf);
    PATH_HOOK_EXIT
}
INTERPOSE(statvfs)(const char *path, struct statvfs *buf){
    PATH_HOOK_ENTER(statvfs)
    auto result = real::statvfs(path, buf);
    PATH_HOOK_EXIT
}
#endif // __APPLE__
INTERPOSE(access)(const char *path, int mode) {
    PATH_HOOK_ENTER(access)
    auto result = real::access(path, mode);
    PATH_HOOK_EXIT
}
INTERPOSE(chdir)(const char *path){
    PATH_HOOK_ENTER(chdir)
    auto result = real::chdir(path);
    PATH_HOOK_EXIT
}
INTERPOSE(chmod)(const char *path, mode_t mode){
    PATH_HOOK_ENTER(chmod)
    auto result = real::chmod(path, mode);
    PATH_HOOK_EXIT
}
INTERPOSE(chown)(const char *path, uid_t uid, gid_t gid){
    PATH_HOOK_ENTER(chown)
    auto result = real::chown(path, uid, gid);
    PATH_HOOK_EXIT
}
INTERPOSE(lchown)(const char *path, uid_t uid, gid_t gid){
    PATH_HOOK_ENTER(lchown)
    auto result = real::lchown(path, uid, gid);
    PATH_HOOK_EXIT
}
INTERPOSE(link)(const char *path1, const char *path2){
    DOUBLE_PATH_HOOK_ENTER(link)
    auto result = real::link(path1, path2);
    DOUBLE_PATH_HOOK_EXIT
}
INTERPOSE(lstat)(const char *path, struct stat *st){
    PATH_HOOK_ENTER(lstat)
    auto result = real::lstat(path, st);
    PATH_HOOK_EXIT
}
INTERPOSE(mkdir)(const char *path, mode_t mode){
    PATH_HOOK_ENTER(mkdir)
    auto result = real::mkdir(path, mode);
    PATH_HOOK_EXIT
}
INTERPOSE(opendir)(const char *path){
    PATH_HOOK_ENTER(opendir)
    auto result = real::opendir(path);
    PATH_HOOK_EXIT
}
INTERPOSE(pathconf)(const char *path, int i){
    PATH_HOOK_ENTER(pathconf)
    auto result = real::pathconf(path, i);
    PATH_HOOK_EXIT
}
INTERPOSE(readlink)(const char *path, char *buf, size_t size){
    PATH_HOOK_ENTER(readlink)
    auto result = real::readlink(path, buf, size);
    PATH_HOOK_EXIT
}
INTERPOSE(realpath)(const char *path, char *resolved){
    PATH_HOOK_ENTER(realpath)
    auto result = real::realpath(path, resolved);
    PATH_HOOK_EXIT
}
INTERPOSE(remove)(const char *path){
    PATH_HOOK_ENTER(remove)
    auto result = real::remove(path);
    PATH_HOOK_EXIT
}
INTERPOSE(rename)(const char *path1, const char *path2){
    DOUBLE_PATH_HOOK_ENTER(rename)
    auto result = real::rename(path1, path2);
    DOUBLE_PATH_HOOK_EXIT
}
INTERPOSE(rmdir)(const char *path){
    PATH_HOOK_ENTER(rmdir)
    auto result = real::rmdir(path);
    PATH_HOOK_EXIT
}
INTERPOSE(stat)(const char *path, struct stat *st){
    PATH_HOOK_ENTER(stat)
    auto result = real::stat(path, st);
    PATH_HOOK_EXIT
}
INTERPOSE(symlink)(const char *path1, const char *path2){
    DOUBLE_PATH_HOOK_ENTER(symlink)
    auto result = real::symlink(path1, path2);
    DOUBLE_PATH_HOOK_EXIT
}
INTERPOSE(truncate)(const char *path, off_t offset){
    PATH_HOOK_ENTER(truncate)
    auto result = real::truncate(path, offset);
    PATH_HOOK_EXIT
}
INTERPOSE(unlink)(const char *path){
    PATH_HOOK_ENTER(unlink)
    auto result = real::unlink(path);
    PATH_HOOK_EXIT
}
INTERPOSE(utime)(const char *path, const struct utimbuf *buf){
    PATH_HOOK_ENTER(utime)
    auto result = real::utime(path, buf);
    PATH_HOOK_EXIT
}
INTERPOSE(utimes)(const char *path, const struct timeval times[2]){
    PATH_HOOK_ENTER(utimes)
    auto result = real::utimes(path, times);
    PATH_HOOK_EXIT
}
#endif // KOALA_LIBC_PATH_HOOK
