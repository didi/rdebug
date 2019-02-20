#ifndef __SPAN_H__
#define __SPAN_H__

#include <stddef.h>

struct ch_span {
    const void *Ptr;
    size_t Len;
};

#endif
