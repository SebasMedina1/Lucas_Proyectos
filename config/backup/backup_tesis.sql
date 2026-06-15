--
-- PostgreSQL database dump
--

-- Dumped from database version 16.2
-- Dumped by pg_dump version 16.2

-- Started on 2026-05-23 09:53:32

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- TOC entry 331 (class 1255 OID 66043)
-- Name: fn_pedido_produccion_ultima_modificacion(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.fn_pedido_produccion_ultima_modificacion() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.pedido_prod_ultima_modificacion = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.fn_pedido_produccion_ultima_modificacion() OWNER TO postgres;

--
-- TOC entry 328 (class 1255 OID 44142)
-- Name: fn_pedidos_compra_ultima_modificacion(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.fn_pedidos_compra_ultima_modificacion() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.pedido_ultima_modificacion = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.fn_pedidos_compra_ultima_modificacion() OWNER TO postgres;

--
-- TOC entry 329 (class 1255 OID 44153)
-- Name: fn_presupuesto_compra_ultima_modificacion(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.fn_presupuesto_compra_ultima_modificacion() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.presu_ultima_modificacion = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.fn_presupuesto_compra_ultima_modificacion() OWNER TO postgres;

--
-- TOC entry 330 (class 1255 OID 43676)
-- Name: obtener_numero_apertura(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.obtener_numero_apertura() RETURNS integer
    LANGUAGE plpgsql
    AS $$
DECLARE
    v_numero integer;
BEGIN
    -- Usamos la tabla nueva apertura_cierre_caja
    SELECT COALESCE(MAX(id_apertura), 0) + 1
    INTO v_numero
    FROM apertura_cierre_caja;

    RETURN v_numero;
END;
$$;


ALTER FUNCTION public.obtener_numero_apertura() OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 253 (class 1259 OID 37776)
-- Name: ajustes; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ajustes (
    id_ajuste integer NOT NULL,
    ajuste_fecha date NOT NULL,
    ajuste_estado character varying(20) NOT NULL,
    id_usuario integer NOT NULL,
    deposito_id integer NOT NULL
);


ALTER TABLE public.ajustes OWNER TO postgres;

--
-- TOC entry 252 (class 1259 OID 37775)
-- Name: ajuste_stock_id_ajuste_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ajuste_stock_id_ajuste_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ajuste_stock_id_ajuste_seq OWNER TO postgres;

--
-- TOC entry 5651 (class 0 OID 0)
-- Dependencies: 252
-- Name: ajuste_stock_id_ajuste_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.ajuste_stock_id_ajuste_seq OWNED BY public.ajustes.id_ajuste;


--
-- TOC entry 313 (class 1259 OID 38126)
-- Name: ajustes_detalle; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ajustes_detalle (
    id_materia_prima integer NOT NULL,
    id_ajuste integer NOT NULL,
    id_stock integer NOT NULL,
    ajuste_cantidad integer NOT NULL,
    id_motivo integer NOT NULL
);


ALTER TABLE public.ajustes_detalle OWNER TO postgres;

--
-- TOC entry 317 (class 1259 OID 39041)
-- Name: bitacora; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.bitacora (
    id_bitacora integer NOT NULL,
    id_usuario integer NOT NULL,
    entidad_afectada character varying(50) NOT NULL,
    id_registro integer,
    accion_realizada character varying(30) NOT NULL,
    descripcion_cambio text,
    fecha_accion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    ip_origen character varying(45),
    navegador_usuario character varying(200),
    estado_registro character varying(10) DEFAULT 'ACTIVO'::character varying,
    CONSTRAINT ck_bitacora_accion CHECK (((accion_realizada)::text = ANY ((ARRAY['ALTA'::character varying, 'MODIFICACION'::character varying, 'ELIMINACION'::character varying, 'INACTIVACION'::character varying])::text[])))
);


ALTER TABLE public.bitacora OWNER TO postgres;

--
-- TOC entry 316 (class 1259 OID 39040)
-- Name: bitacora_id_bitacora_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.bitacora_id_bitacora_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.bitacora_id_bitacora_seq OWNER TO postgres;

--
-- TOC entry 5652 (class 0 OID 0)
-- Dependencies: 316
-- Name: bitacora_id_bitacora_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.bitacora_id_bitacora_seq OWNED BY public.bitacora.id_bitacora;


--
-- TOC entry 251 (class 1259 OID 37769)
-- Name: cargos; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cargos (
    id_cargo integer NOT NULL,
    cargo_descripcion character varying(30) NOT NULL,
    estado_cargo character varying(30) NOT NULL,
    id_usuario integer
);


ALTER TABLE public.cargos OWNER TO postgres;

--
-- TOC entry 250 (class 1259 OID 37768)
-- Name: cargos_id_cargo_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cargos_id_cargo_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cargos_id_cargo_seq OWNER TO postgres;

--
-- TOC entry 5653 (class 0 OID 0)
-- Dependencies: 250
-- Name: cargos_id_cargo_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cargos_id_cargo_seq OWNED BY public.cargos.id_cargo;


--
-- TOC entry 232 (class 1259 OID 37682)
-- Name: conductores; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.conductores (
    conductor_id integer NOT NULL,
    conductor_nombre character varying(30) NOT NULL,
    conductor_apellido character varying(30) NOT NULL,
    conductor_telefono character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.conductores OWNER TO postgres;

--
-- TOC entry 231 (class 1259 OID 37681)
-- Name: conductores_conductor_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.conductores_conductor_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.conductores_conductor_id_seq OWNER TO postgres;

--
-- TOC entry 5654 (class 0 OID 0)
-- Dependencies: 231
-- Name: conductores_conductor_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.conductores_conductor_id_seq OWNED BY public.conductores.conductor_id;


--
-- TOC entry 299 (class 1259 OID 38059)
-- Name: control_calidad_detalle; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.control_calidad_detalle (
    calidad_id integer NOT NULL,
    producto_id integer NOT NULL,
    calidad_estado character varying(30) NOT NULL,
    calidad_cantidad integer NOT NULL,
    parametro_id integer NOT NULL,
    valor_medido character varying(100),
    cumple_parametro boolean
);


ALTER TABLE public.control_calidad_detalle OWNER TO postgres;

--
-- TOC entry 271 (class 1259 OID 37855)
-- Name: control_calidad_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.control_calidad_produccion (
    calidad_id integer NOT NULL,
    calidad_fecha date NOT NULL,
    calidad_estado character varying(30) NOT NULL,
    id_inspectores integer NOT NULL,
    id_usuario integer NOT NULL,
    terminado_id integer NOT NULL
);


ALTER TABLE public.control_calidad_produccion OWNER TO postgres;

--
-- TOC entry 270 (class 1259 OID 37854)
-- Name: control_calidad_produccion_calidad_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.control_calidad_produccion_calidad_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.control_calidad_produccion_calidad_id_seq OWNER TO postgres;

--
-- TOC entry 5655 (class 0 OID 0)
-- Dependencies: 270
-- Name: control_calidad_produccion_calidad_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.control_calidad_produccion_calidad_id_seq OWNED BY public.control_calidad_produccion.calidad_id;


--
-- TOC entry 273 (class 1259 OID 37862)
-- Name: control_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.control_produccion (
    control_id integer NOT NULL,
    control_fecha date NOT NULL,
    control_estado character varying(30) NOT NULL,
    id_inspectores integer NOT NULL,
    orden_id integer NOT NULL,
    id_usuario integer NOT NULL,
    producto_id integer,
    etapa_id integer,
    control_observacion text,
    CONSTRAINT control_produccion_estado_chk CHECK (((control_estado)::text = ANY ((ARRAY['REGISTRADO'::character varying, 'ANULADO'::character varying])::text[])))
);


ALTER TABLE public.control_produccion OWNER TO postgres;

--
-- TOC entry 327 (class 1259 OID 66159)
-- Name: control_produccion_consumo; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.control_produccion_consumo (
    control_id integer NOT NULL,
    id_materia_prima integer NOT NULL,
    cantidad_consumida numeric(12,3) NOT NULL,
    CONSTRAINT control_produccion_consumo_cant_chk CHECK ((cantidad_consumida > (0)::numeric))
);


ALTER TABLE public.control_produccion_consumo OWNER TO postgres;

--
-- TOC entry 5656 (class 0 OID 0)
-- Dependencies: 327
-- Name: TABLE control_produccion_consumo; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.control_produccion_consumo IS 'Materia prima consumida al registrar avance en control de producción.';


--
-- TOC entry 272 (class 1259 OID 37861)
-- Name: control_produccion_control_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.control_produccion_control_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.control_produccion_control_id_seq OWNER TO postgres;

--
-- TOC entry 5657 (class 0 OID 0)
-- Dependencies: 272
-- Name: control_produccion_control_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.control_produccion_control_id_seq OWNED BY public.control_produccion.control_id;


--
-- TOC entry 295 (class 1259 OID 38018)
-- Name: control_produccion_detalle; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.control_produccion_detalle (
    control_id integer NOT NULL,
    producto_id integer NOT NULL,
    control_cantidad integer NOT NULL,
    control_descri character varying(30) NOT NULL
);


ALTER TABLE public.control_produccion_detalle OWNER TO postgres;

--
-- TOC entry 304 (class 1259 OID 38078)
-- Name: costo_detalle_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.costo_detalle_produccion (
    costo_id integer NOT NULL,
    id_materia_prima integer,
    costo_cantidad integer NOT NULL,
    costo_precio integer NOT NULL,
    costo_detalle_id integer NOT NULL,
    costo_tipo character varying(10) DEFAULT 'MP'::character varying NOT NULL,
    trabajadores_id integer,
    costo_concepto character varying(150),
    CONSTRAINT costo_detalle_tipo_chk CHECK (((costo_tipo)::text = ANY ((ARRAY['MP'::character varying, 'MO'::character varying, 'CIF'::character varying])::text[]))),
    CONSTRAINT costo_detalle_tipo_mp_chk CHECK (((((costo_tipo)::text = 'MP'::text) AND (id_materia_prima IS NOT NULL)) OR ((costo_tipo)::text = ANY ((ARRAY['MO'::character varying, 'CIF'::character varying])::text[]))))
);


ALTER TABLE public.costo_detalle_produccion OWNER TO postgres;

--
-- TOC entry 326 (class 1259 OID 66113)
-- Name: costo_detalle_produccion_costo_detalle_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.costo_detalle_produccion_costo_detalle_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.costo_detalle_produccion_costo_detalle_id_seq OWNER TO postgres;

--
-- TOC entry 5658 (class 0 OID 0)
-- Dependencies: 326
-- Name: costo_detalle_produccion_costo_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.costo_detalle_produccion_costo_detalle_id_seq OWNED BY public.costo_detalle_produccion.costo_detalle_id;


--
-- TOC entry 259 (class 1259 OID 37806)
-- Name: costo_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.costo_produccion (
    costo_id integer NOT NULL,
    costo_fecha date NOT NULL,
    costo_estado character varying(30) NOT NULL,
    costo_total integer NOT NULL,
    id_usuario integer NOT NULL,
    orden_id integer,
    CONSTRAINT costo_produccion_estado_chk CHECK (((costo_estado)::text = ANY ((ARRAY['PENDIENTE'::character varying, 'CERRADO'::character varying, 'ANULADO'::character varying])::text[])))
);


ALTER TABLE public.costo_produccion OWNER TO postgres;

--
-- TOC entry 5659 (class 0 OID 0)
-- Dependencies: 259
-- Name: COLUMN costo_produccion.orden_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.costo_produccion.orden_id IS 'Orden de producción costeada (MP + MO + CIF).';


--
-- TOC entry 258 (class 1259 OID 37805)
-- Name: costo_produccion_costo_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.costo_produccion_costo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.costo_produccion_costo_id_seq OWNER TO postgres;

--
-- TOC entry 5660 (class 0 OID 0)
-- Dependencies: 258
-- Name: costo_produccion_costo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.costo_produccion_costo_id_seq OWNED BY public.costo_produccion.costo_id;


--
-- TOC entry 255 (class 1259 OID 37783)
-- Name: cuentas_pagar; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cuentas_pagar (
    id_cuenta_pagar integer NOT NULL,
    monto_total integer NOT NULL,
    monto_pendiente integer NOT NULL,
    estado character varying(15) NOT NULL,
    fecha_emision date NOT NULL,
    fecha_vencimiento date NOT NULL,
    id_sucursal integer NOT NULL,
    id_usuario integer NOT NULL,
    id_proveedor integer NOT NULL,
    id_factura_compra integer,
    plazo_cuenta integer DEFAULT 0 NOT NULL,
    nro_cuota integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.cuentas_pagar OWNER TO postgres;

--
-- TOC entry 254 (class 1259 OID 37782)
-- Name: cuentas_pagar_id_cuenta_pagar_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cuentas_pagar_id_cuenta_pagar_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cuentas_pagar_id_cuenta_pagar_seq OWNER TO postgres;

--
-- TOC entry 5661 (class 0 OID 0)
-- Dependencies: 254
-- Name: cuentas_pagar_id_cuenta_pagar_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cuentas_pagar_id_cuenta_pagar_seq OWNED BY public.cuentas_pagar.id_cuenta_pagar;


--
-- TOC entry 234 (class 1259 OID 37689)
-- Name: deposito; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.deposito (
    deposito_id integer NOT NULL,
    deposito_descri character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.deposito OWNER TO postgres;

--
-- TOC entry 233 (class 1259 OID 37688)
-- Name: deposito_deposito_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.deposito_deposito_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.deposito_deposito_id_seq OWNER TO postgres;

--
-- TOC entry 5662 (class 0 OID 0)
-- Dependencies: 233
-- Name: deposito_deposito_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.deposito_deposito_id_seq OWNED BY public.deposito.deposito_id;


--
-- TOC entry 239 (class 1259 OID 37716)
-- Name: equipo_detalle; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.equipo_detalle (
    equipo_id integer NOT NULL,
    trabajadores_id integer NOT NULL,
    tarea_rol character varying(100) DEFAULT 'Operario'::character varying NOT NULL
);


ALTER TABLE public.equipo_detalle OWNER TO postgres;

--
-- TOC entry 5663 (class 0 OID 0)
-- Dependencies: 239
-- Name: COLUMN equipo_detalle.tarea_rol; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.equipo_detalle.tarea_rol IS 'Rol o tarea del trabajador en la orden (armado, cocción, empaque, etc.).';


--
-- TOC entry 222 (class 1259 OID 37626)
-- Name: equipos_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.equipos_produccion (
    equipo_id integer NOT NULL,
    equipo_descri character varying(30) NOT NULL,
    id_usuario integer NOT NULL,
    equipo_estado character varying(30) NOT NULL,
    orden_id integer,
    equipo_fecha date DEFAULT CURRENT_DATE,
    id_sucursal integer,
    CONSTRAINT equipos_produccion_estado_chk CHECK (((equipo_estado)::text = ANY ((ARRAY['PENDIENTE'::character varying, 'ACTIVO'::character varying, 'ANULADO'::character varying])::text[])))
);


ALTER TABLE public.equipos_produccion OWNER TO postgres;

--
-- TOC entry 5664 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN equipos_produccion.equipo_descri; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.equipos_produccion.equipo_descri IS 'Nombre del equipo (ej. Equipo OP-001 / Turno A).';


--
-- TOC entry 221 (class 1259 OID 37625)
-- Name: equipos_produccion_equipo_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.equipos_produccion_equipo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.equipos_produccion_equipo_id_seq OWNER TO postgres;

--
-- TOC entry 5665 (class 0 OID 0)
-- Dependencies: 221
-- Name: equipos_produccion_equipo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.equipos_produccion_equipo_id_seq OWNED BY public.equipos_produccion.equipo_id;


--
-- TOC entry 292 (class 1259 OID 38003)
-- Name: etapa_detalle_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.etapa_detalle_produccion (
    etapa_id integer NOT NULL,
    producto_id integer NOT NULL,
    etapa_nombre character varying(30) NOT NULL,
    etapa_procedimiento text NOT NULL,
    etapa_secuencia integer DEFAULT 1 NOT NULL,
    etapa_tiempo_estimado integer,
    etapa_observaciones text
);


ALTER TABLE public.etapa_detalle_produccion OWNER TO postgres;

--
-- TOC entry 5666 (class 0 OID 0)
-- Dependencies: 292
-- Name: COLUMN etapa_detalle_produccion.etapa_tiempo_estimado; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.etapa_detalle_produccion.etapa_tiempo_estimado IS 'Tiempo estimado en minutos para la etapa.';


--
-- TOC entry 220 (class 1259 OID 37619)
-- Name: etapa_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.etapa_produccion (
    etapa_id integer NOT NULL,
    etapa_fecha date NOT NULL,
    etapa_descri character varying(30) NOT NULL,
    id_usuario integer NOT NULL,
    producto_id integer,
    etapa_estado character varying(30) DEFAULT 'ACTIVA'::character varying NOT NULL,
    CONSTRAINT etapa_produccion_estado_chk CHECK (((etapa_estado)::text = ANY ((ARRAY['ACTIVA'::character varying, 'ANULADA'::character varying])::text[])))
);


ALTER TABLE public.etapa_produccion OWNER TO postgres;

--
-- TOC entry 219 (class 1259 OID 37618)
-- Name: etapa_produccion_etapa_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.etapa_produccion_etapa_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.etapa_produccion_etapa_id_seq OWNER TO postgres;

--
-- TOC entry 5667 (class 0 OID 0)
-- Dependencies: 219
-- Name: etapa_produccion_etapa_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.etapa_produccion_etapa_id_seq OWNED BY public.etapa_produccion.etapa_id;


--
-- TOC entry 279 (class 1259 OID 37918)
-- Name: factura_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.factura_compra (
    id_factura_compra integer NOT NULL,
    numero_factura character varying(30) NOT NULL,
    timbrado character varying(30) NOT NULL,
    fac_fecha_vencimiento date NOT NULL,
    fact_fecha_compra date NOT NULL,
    fac_total integer NOT NULL,
    fac_estado character varying(20) NOT NULL,
    fac_plazo character varying(30) NOT NULL,
    fac_remision double precision NOT NULL,
    id_usuario integer NOT NULL,
    id_sucursal integer NOT NULL,
    id_orden_compra integer NOT NULL,
    tipo_operacion character varying(30)
);


ALTER TABLE public.factura_compra OWNER TO postgres;

--
-- TOC entry 278 (class 1259 OID 37917)
-- Name: factura_compra_id_factura_compra_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.factura_compra_id_factura_compra_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.factura_compra_id_factura_compra_seq OWNER TO postgres;

--
-- TOC entry 5668 (class 0 OID 0)
-- Dependencies: 278
-- Name: factura_compra_id_factura_compra_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.factura_compra_id_factura_compra_seq OWNED BY public.factura_compra.id_factura_compra;


--
-- TOC entry 310 (class 1259 OID 38112)
-- Name: factura_detalle_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.factura_detalle_compra (
    id_materia_prima integer NOT NULL,
    id_factura_compra integer NOT NULL,
    fac_cantidad integer NOT NULL,
    fac_iva numeric NOT NULL,
    fac_precio integer NOT NULL
);


ALTER TABLE public.factura_detalle_compra OWNER TO postgres;

--
-- TOC entry 319 (class 1259 OID 44083)
-- Name: historial_productos; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.historial_productos (
    id_historial integer NOT NULL,
    producto_id integer NOT NULL,
    campo_modificado character varying(50) NOT NULL,
    valor_anterior text,
    valor_nuevo text,
    fecha_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    id_usuario integer NOT NULL,
    accion character varying(30) NOT NULL
);


ALTER TABLE public.historial_productos OWNER TO postgres;

--
-- TOC entry 5669 (class 0 OID 0)
-- Dependencies: 319
-- Name: TABLE historial_productos; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.historial_productos IS 'Historial de cambios en productos (precios, estados, etc.)';


--
-- TOC entry 318 (class 1259 OID 44082)
-- Name: historial_productos_id_historial_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.historial_productos_id_historial_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.historial_productos_id_historial_seq OWNER TO postgres;

--
-- TOC entry 5670 (class 0 OID 0)
-- Dependencies: 318
-- Name: historial_productos_id_historial_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.historial_productos_id_historial_seq OWNED BY public.historial_productos.id_historial;


--
-- TOC entry 236 (class 1259 OID 37703)
-- Name: inspectores; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.inspectores (
    id_inspectores integer NOT NULL,
    id_personal integer NOT NULL,
    inspector_estado character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.inspectores OWNER TO postgres;

--
-- TOC entry 235 (class 1259 OID 37702)
-- Name: inspectores_id_inspectores_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.inspectores_id_inspectores_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inspectores_id_inspectores_seq OWNER TO postgres;

--
-- TOC entry 5671 (class 0 OID 0)
-- Dependencies: 235
-- Name: inspectores_id_inspectores_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.inspectores_id_inspectores_seq OWNED BY public.inspectores.id_inspectores;


--
-- TOC entry 281 (class 1259 OID 37925)
-- Name: iva_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.iva_compra (
    iva_id integer NOT NULL,
    id_factura_compra integer NOT NULL,
    iva_fecha date NOT NULL,
    iva_exento numeric NOT NULL,
    iva_5 numeric NOT NULL,
    iva_10 numeric NOT NULL
);


ALTER TABLE public.iva_compra OWNER TO postgres;

--
-- TOC entry 280 (class 1259 OID 37924)
-- Name: iva_compra_id_libro_compra_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.iva_compra_id_libro_compra_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.iva_compra_id_libro_compra_seq OWNER TO postgres;

--
-- TOC entry 5672 (class 0 OID 0)
-- Dependencies: 280
-- Name: iva_compra_id_libro_compra_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.iva_compra_id_libro_compra_seq OWNED BY public.iva_compra.iva_id;


--
-- TOC entry 303 (class 1259 OID 38072)
-- Name: materia_prima; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.materia_prima (
    id_materia_prima integer NOT NULL,
    materia_prima_descripcion character varying(30) NOT NULL,
    materia_prima_estado character varying(30) NOT NULL,
    id_unidad integer NOT NULL,
    id_usuario integer NOT NULL,
    iva_id integer NOT NULL
);


ALTER TABLE public.materia_prima OWNER TO postgres;

--
-- TOC entry 302 (class 1259 OID 38071)
-- Name: materia_prima_id_materia_prima_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.materia_prima_id_materia_prima_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.materia_prima_id_materia_prima_seq OWNER TO postgres;

--
-- TOC entry 5673 (class 0 OID 0)
-- Dependencies: 302
-- Name: materia_prima_id_materia_prima_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.materia_prima_id_materia_prima_seq OWNED BY public.materia_prima.id_materia_prima;


--
-- TOC entry 241 (class 1259 OID 37722)
-- Name: modulos; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.modulos (
    modulo_id integer NOT NULL,
    modulo_descri character varying(30) NOT NULL,
    id_usuario integer
);


ALTER TABLE public.modulos OWNER TO postgres;

--
-- TOC entry 240 (class 1259 OID 37721)
-- Name: modulos_modulo_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.modulos_modulo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.modulos_modulo_id_seq OWNER TO postgres;

--
-- TOC entry 5674 (class 0 OID 0)
-- Dependencies: 240
-- Name: modulos_modulo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.modulos_modulo_id_seq OWNED BY public.modulos.modulo_id;


--
-- TOC entry 285 (class 1259 OID 37969)
-- Name: motivo; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.motivo (
    id_motivo integer NOT NULL,
    motivo_descripcion character varying(30) NOT NULL,
    id_usuario integer NOT NULL,
    categoria_motivo character varying(20) DEFAULT 'NOTA_CREDITO'::character varying
);


ALTER TABLE public.motivo OWNER TO postgres;

--
-- TOC entry 5675 (class 0 OID 0)
-- Dependencies: 285
-- Name: COLUMN motivo.categoria_motivo; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.motivo.categoria_motivo IS 'Categoría del motivo: AJUSTE (para ajustes de inventario), NOTA_CREDITO (para notas de crédito/débito)';


--
-- TOC entry 284 (class 1259 OID 37968)
-- Name: motivo_id_motivo_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.motivo_id_motivo_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.motivo_id_motivo_seq OWNER TO postgres;

--
-- TOC entry 5676 (class 0 OID 0)
-- Dependencies: 284
-- Name: motivo_id_motivo_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.motivo_id_motivo_seq OWNED BY public.motivo.id_motivo;


--
-- TOC entry 287 (class 1259 OID 37983)
-- Name: nota_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.nota_compra (
    id_nota_compra integer NOT NULL,
    nota_compra_tipo character varying(50) NOT NULL,
    nota_compra_fecha date NOT NULL,
    nota_nro bigint NOT NULL,
    nota_compra_timbrado character varying(30) NOT NULL,
    nota_compra_inicio date NOT NULL,
    nota_compra_vencimiento date NOT NULL,
    nota_compra_estado character varying(20) NOT NULL,
    nota_total integer NOT NULL,
    id_usuario integer NOT NULL,
    id_sucursal integer NOT NULL,
    id_proveedor integer NOT NULL,
    id_motivo integer NOT NULL,
    id_factura_compra integer
);


ALTER TABLE public.nota_compra OWNER TO postgres;

--
-- TOC entry 286 (class 1259 OID 37982)
-- Name: nota_compra_id_nota_compra_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.nota_compra_id_nota_compra_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.nota_compra_id_nota_compra_seq OWNER TO postgres;

--
-- TOC entry 5677 (class 0 OID 0)
-- Dependencies: 286
-- Name: nota_compra_id_nota_compra_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.nota_compra_id_nota_compra_seq OWNED BY public.nota_compra.id_nota_compra;


--
-- TOC entry 309 (class 1259 OID 38105)
-- Name: nota_detalle_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.nota_detalle_compra (
    id_materia_prima integer NOT NULL,
    id_nota_compra integer NOT NULL,
    nota_compra_cantidad integer NOT NULL,
    tipo_iva numeric NOT NULL,
    nota_precio integer NOT NULL
);


ALTER TABLE public.nota_detalle_compra OWNER TO postgres;

--
-- TOC entry 283 (class 1259 OID 37934)
-- Name: nota_remision_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.nota_remision_compra (
    id_nota_remision integer NOT NULL,
    id_factura_compra integer,
    nota_fecha date NOT NULL,
    nota_remision_total integer NOT NULL,
    nota_remision_nro character varying(20) NOT NULL,
    nota_estado character varying(30) NOT NULL,
    id_usuario integer NOT NULL,
    id_proveedor integer NOT NULL,
    deposito_id integer NOT NULL,
    conductor_id integer NOT NULL,
    vehiculo_id integer NOT NULL,
    id_orden_compra integer
);


ALTER TABLE public.nota_remision_compra OWNER TO postgres;

--
-- TOC entry 5678 (class 0 OID 0)
-- Dependencies: 283
-- Name: COLUMN nota_remision_compra.nota_remision_nro; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.nota_remision_compra.nota_remision_nro IS 'Número legal de remisión. Puede ser formato EEE-PPP-NNNNNNN o 13 dígitos.';


--
-- TOC entry 5679 (class 0 OID 0)
-- Dependencies: 283
-- Name: COLUMN nota_remision_compra.id_orden_compra; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.nota_remision_compra.id_orden_compra IS 'Orden de Compra asociada directamente. Permite acceso sin pasar por factura_compra.';


--
-- TOC entry 282 (class 1259 OID 37933)
-- Name: nota_remision_compra_id_nota_remision_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.nota_remision_compra_id_nota_remision_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.nota_remision_compra_id_nota_remision_seq OWNER TO postgres;

--
-- TOC entry 5680 (class 0 OID 0)
-- Dependencies: 282
-- Name: nota_remision_compra_id_nota_remision_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.nota_remision_compra_id_nota_remision_seq OWNED BY public.nota_remision_compra.id_nota_remision;


--
-- TOC entry 308 (class 1259 OID 38098)
-- Name: nota_remision_detalle_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.nota_remision_detalle_compra (
    id_nota_remision integer NOT NULL,
    id_materia_prima integer NOT NULL,
    nota_cantidad integer NOT NULL,
    nota_remi_iva numeric NOT NULL
);


ALTER TABLE public.nota_remision_detalle_compra OWNER TO postgres;

--
-- TOC entry 265 (class 1259 OID 37827)
-- Name: orden_de_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.orden_de_compra (
    id_orden_compra integer NOT NULL,
    orden_fecha date NOT NULL,
    orden_estado character varying(30) NOT NULL,
    orden_total integer NOT NULL,
    id_presupuesto_compra integer NOT NULL,
    id_proveedor integer NOT NULL,
    id_sucursal integer NOT NULL,
    id_usuario integer NOT NULL,
    orden_condicion character varying(20) DEFAULT 'CONTADO'::character varying NOT NULL
);


ALTER TABLE public.orden_de_compra OWNER TO postgres;

--
-- TOC entry 5681 (class 0 OID 0)
-- Dependencies: 265
-- Name: COLUMN orden_de_compra.orden_condicion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.orden_de_compra.orden_condicion IS 'Condición de pago: CONTADO o CREDITO';


--
-- TOC entry 264 (class 1259 OID 37826)
-- Name: orden_de_compra_id_orden_compra_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.orden_de_compra_id_orden_compra_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.orden_de_compra_id_orden_compra_seq OWNER TO postgres;

--
-- TOC entry 5682 (class 0 OID 0)
-- Dependencies: 264
-- Name: orden_de_compra_id_orden_compra_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.orden_de_compra_id_orden_compra_seq OWNED BY public.orden_de_compra.id_orden_compra;


--
-- TOC entry 314 (class 1259 OID 38131)
-- Name: orden_detalle_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.orden_detalle_compra (
    id_materia_prima integer NOT NULL,
    id_orden_compra integer NOT NULL,
    oc_cantidad_compra integer NOT NULL,
    oc_precio_compra integer NOT NULL
);


ALTER TABLE public.orden_detalle_compra OWNER TO postgres;

--
-- TOC entry 296 (class 1259 OID 38023)
-- Name: orden_detalle_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.orden_detalle_produccion (
    orden_id integer NOT NULL,
    producto_id integer NOT NULL,
    orden_prod_cantidad integer NOT NULL,
    cantidad_pendiente integer
);


ALTER TABLE public.orden_detalle_produccion OWNER TO postgres;

--
-- TOC entry 5683 (class 0 OID 0)
-- Dependencies: 296
-- Name: COLUMN orden_detalle_produccion.cantidad_pendiente; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.orden_detalle_produccion.cantidad_pendiente IS 'Cantidad aún no procesada en control de producción / PT.';


--
-- TOC entry 267 (class 1259 OID 37841)
-- Name: orden_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.orden_produccion (
    orden_id integer NOT NULL,
    orden_prod_fecha date NOT NULL,
    orden_prod_fecha_entrega date,
    orden_prod_estado character varying(30) NOT NULL,
    id_usuario integer NOT NULL,
    id_pedido_produccion integer NOT NULL,
    CONSTRAINT orden_produccion_estado_chk CHECK (((orden_prod_estado)::text = ANY ((ARRAY['PENDIENTE'::character varying, 'EN_PROCESO'::character varying, 'TERMINADA'::character varying, 'ANULADA'::character varying])::text[])))
);


ALTER TABLE public.orden_produccion OWNER TO postgres;

--
-- TOC entry 5684 (class 0 OID 0)
-- Dependencies: 267
-- Name: TABLE orden_produccion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.orden_produccion IS 'Orden de producción generada desde pedido_produccion.';


--
-- TOC entry 5685 (class 0 OID 0)
-- Dependencies: 267
-- Name: COLUMN orden_produccion.id_pedido_produccion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.orden_produccion.id_pedido_produccion IS 'Pedido de producción que origina esta orden (flujo: pedido → orden → control / PT).';


--
-- TOC entry 266 (class 1259 OID 37840)
-- Name: orden_produccion_orden_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.orden_produccion_orden_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.orden_produccion_orden_id_seq OWNER TO postgres;

--
-- TOC entry 5686 (class 0 OID 0)
-- Dependencies: 266
-- Name: orden_produccion_orden_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.orden_produccion_orden_id_seq OWNED BY public.orden_produccion.orden_id;


--
-- TOC entry 298 (class 1259 OID 38053)
-- Name: parametros_control; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.parametros_control (
    parametro_id integer NOT NULL,
    parametro_descri character varying(30) NOT NULL,
    producto_id integer NOT NULL,
    id_usuario integer NOT NULL,
    parametro_estado character varying(30) DEFAULT 'ACTIVO'::character varying NOT NULL,
    CONSTRAINT parametros_control_estado_chk CHECK (((parametro_estado)::text = ANY ((ARRAY['ACTIVO'::character varying, 'INACTIVO'::character varying])::text[])))
);


ALTER TABLE public.parametros_control OWNER TO postgres;

--
-- TOC entry 297 (class 1259 OID 38052)
-- Name: parametros_control_parametro_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.parametros_control_parametro_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.parametros_control_parametro_id_seq OWNER TO postgres;

--
-- TOC entry 5687 (class 0 OID 0)
-- Dependencies: 297
-- Name: parametros_control_parametro_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.parametros_control_parametro_id_seq OWNED BY public.parametros_control.parametro_id;


--
-- TOC entry 307 (class 1259 OID 38093)
-- Name: pedido_detalle_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pedido_detalle_compra (
    id_pedido_compra integer NOT NULL,
    id_materia_prima integer NOT NULL,
    cantidad_pedido integer NOT NULL
);


ALTER TABLE public.pedido_detalle_compra OWNER TO postgres;

--
-- TOC entry 325 (class 1259 OID 66012)
-- Name: pedido_detalle_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pedido_detalle_produccion (
    id_pedido_produccion integer NOT NULL,
    producto_id integer NOT NULL,
    cantidad_pedido integer NOT NULL,
    CONSTRAINT pedido_detalle_produccion_cantidad_chk CHECK ((cantidad_pedido > 0))
);


ALTER TABLE public.pedido_detalle_produccion OWNER TO postgres;

--
-- TOC entry 5688 (class 0 OID 0)
-- Dependencies: 325
-- Name: TABLE pedido_detalle_produccion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.pedido_detalle_produccion IS 'Ítems del pedido: producto terminado a producir y cantidad solicitada.';


--
-- TOC entry 306 (class 1259 OID 38088)
-- Name: pedido_materia_detalle_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pedido_materia_detalle_produccion (
    id_pedido_mat_prod integer NOT NULL,
    id_materia_prima integer NOT NULL,
    ped_mat_prod_cantidad integer NOT NULL,
    cantidad_repuesta integer DEFAULT 0 NOT NULL,
    CONSTRAINT pedido_materia_detalle_cantidad_chk CHECK (((ped_mat_prod_cantidad > 0) AND (cantidad_repuesta >= 0) AND (cantidad_repuesta <= ped_mat_prod_cantidad)))
);


ALTER TABLE public.pedido_materia_detalle_produccion OWNER TO postgres;

--
-- TOC entry 5689 (class 0 OID 0)
-- Dependencies: 306
-- Name: COLUMN pedido_materia_detalle_produccion.ped_mat_prod_cantidad; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pedido_materia_detalle_produccion.ped_mat_prod_cantidad IS 'Cantidad solicitada.';


--
-- TOC entry 5690 (class 0 OID 0)
-- Dependencies: 306
-- Name: COLUMN pedido_materia_detalle_produccion.cantidad_repuesta; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pedido_materia_detalle_produccion.cantidad_repuesta IS 'Cantidad ya repuesta vía reposicion_materia.';


--
-- TOC entry 228 (class 1259 OID 37668)
-- Name: pedido_materia_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pedido_materia_produccion (
    id_pedido_mat_prod integer NOT NULL,
    ped_mat_prod_fecha date NOT NULL,
    ped_mat_prod_estado character varying(30) NOT NULL,
    id_usuario integer NOT NULL,
    id_sucursal integer NOT NULL,
    deposito_id integer NOT NULL,
    CONSTRAINT pedido_materia_produccion_estado_chk CHECK (((ped_mat_prod_estado)::text = ANY ((ARRAY['PENDIENTE'::character varying, 'PARCIAL'::character varying, 'COMPLETADO'::character varying, 'ANULADO'::character varying])::text[])))
);


ALTER TABLE public.pedido_materia_produccion OWNER TO postgres;

--
-- TOC entry 5691 (class 0 OID 0)
-- Dependencies: 228
-- Name: TABLE pedido_materia_produccion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.pedido_materia_produccion IS 'Solicitud de MP hacia un depósito; no mueve stock hasta la reposición.';


--
-- TOC entry 227 (class 1259 OID 37667)
-- Name: pedido_materia_produccion_id_pedido_mat_prod_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pedido_materia_produccion_id_pedido_mat_prod_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pedido_materia_produccion_id_pedido_mat_prod_seq OWNER TO postgres;

--
-- TOC entry 5692 (class 0 OID 0)
-- Dependencies: 227
-- Name: pedido_materia_produccion_id_pedido_mat_prod_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pedido_materia_produccion_id_pedido_mat_prod_seq OWNED BY public.pedido_materia_produccion.id_pedido_mat_prod;


--
-- TOC entry 323 (class 1259 OID 66001)
-- Name: pedido_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pedido_produccion (
    id_pedido_produccion integer NOT NULL,
    pedido_prod_fecha_emision date NOT NULL,
    pedido_prod_estado character varying(30) NOT NULL,
    id_tipo_pedido integer NOT NULL,
    id_usuario integer NOT NULL,
    id_sucursal integer NOT NULL,
    pedido_prod_observaciones text,
    pedido_prod_ultima_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pedido_produccion_estado_chk CHECK (((pedido_prod_estado)::text = ANY ((ARRAY['PENDIENTE'::character varying, 'ASIGNADO'::character varying, 'ANULADO'::character varying])::text[])))
);


ALTER TABLE public.pedido_produccion OWNER TO postgres;

--
-- TOC entry 5693 (class 0 OID 0)
-- Dependencies: 323
-- Name: TABLE pedido_produccion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.pedido_produccion IS 'Inicio del flujo de producción: qué productos elaborar y en qué cantidad.';


--
-- TOC entry 5694 (class 0 OID 0)
-- Dependencies: 323
-- Name: COLUMN pedido_produccion.pedido_prod_estado; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pedido_produccion.pedido_prod_estado IS 'PENDIENTE → listo para generar OP; ASIGNADO → vinculado a orden_produccion; ANULADO.';


--
-- TOC entry 324 (class 1259 OID 66010)
-- Name: pedido_produccion_id_pedido_produccion_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pedido_produccion_id_pedido_produccion_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pedido_produccion_id_pedido_produccion_seq OWNER TO postgres;

--
-- TOC entry 5695 (class 0 OID 0)
-- Dependencies: 324
-- Name: pedido_produccion_id_pedido_produccion_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pedido_produccion_id_pedido_produccion_seq OWNED BY public.pedido_produccion.id_pedido_produccion;


--
-- TOC entry 257 (class 1259 OID 37799)
-- Name: pedidos_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pedidos_compra (
    id_pedido_compra integer NOT NULL,
    pedido_fecha_emision date NOT NULL,
    pedido_estado character varying(20) NOT NULL,
    id_usuario integer NOT NULL,
    id_sucursal integer NOT NULL,
    pedido_observaciones text,
    pedido_ultima_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.pedidos_compra OWNER TO postgres;

--
-- TOC entry 5696 (class 0 OID 0)
-- Dependencies: 257
-- Name: COLUMN pedidos_compra.pedido_observaciones; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pedidos_compra.pedido_observaciones IS 'Observaciones o notas adicionales del pedido de compra';


--
-- TOC entry 5697 (class 0 OID 0)
-- Dependencies: 257
-- Name: COLUMN pedidos_compra.pedido_ultima_modificacion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pedidos_compra.pedido_ultima_modificacion IS 'Timestamp de última modificación para control de concurrencia';


--
-- TOC entry 256 (class 1259 OID 37798)
-- Name: pedidos_compra_id_pedido_compra_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pedidos_compra_id_pedido_compra_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pedidos_compra_id_pedido_compra_seq OWNER TO postgres;

--
-- TOC entry 5698 (class 0 OID 0)
-- Dependencies: 256
-- Name: pedidos_compra_id_pedido_compra_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pedidos_compra_id_pedido_compra_seq OWNED BY public.pedidos_compra.id_pedido_compra;


--
-- TOC entry 275 (class 1259 OID 37869)
-- Name: perdidas; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.perdidas (
    perdidas_id integer NOT NULL,
    perdida_estado character varying(30) NOT NULL,
    perdida_fecha date NOT NULL,
    tipo_perdida_id integer NOT NULL,
    calidad_id integer,
    control_id integer,
    id_usuario integer NOT NULL,
    CONSTRAINT perdidas_origen_chk CHECK ((((control_id IS NOT NULL) AND (calidad_id IS NULL)) OR ((control_id IS NULL) AND (calidad_id IS NOT NULL))))
);


ALTER TABLE public.perdidas OWNER TO postgres;

--
-- TOC entry 293 (class 1259 OID 38008)
-- Name: perdidas_detalle; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.perdidas_detalle (
    perdidas_id integer NOT NULL,
    producto_id integer NOT NULL,
    perdida_cantidad integer NOT NULL,
    perdida_motivo character varying(30) NOT NULL
);


ALTER TABLE public.perdidas_detalle OWNER TO postgres;

--
-- TOC entry 274 (class 1259 OID 37868)
-- Name: perdidas_perdidas_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.perdidas_perdidas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.perdidas_perdidas_id_seq OWNER TO postgres;

--
-- TOC entry 5699 (class 0 OID 0)
-- Dependencies: 274
-- Name: perdidas_perdidas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.perdidas_perdidas_id_seq OWNED BY public.perdidas.perdidas_id;


--
-- TOC entry 216 (class 1259 OID 37605)
-- Name: personal; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.personal (
    id_personal integer NOT NULL,
    personal_estado character varying(30) NOT NULL,
    personal_nombre character varying(30) NOT NULL,
    personal_apellido character varying(30) NOT NULL,
    personal_telefono character varying(30) NOT NULL,
    personal_ci character varying(30) NOT NULL,
    id_cargo integer NOT NULL,
    id_usuario integer,
    id_sucursal integer
);


ALTER TABLE public.personal OWNER TO postgres;

--
-- TOC entry 5700 (class 0 OID 0)
-- Dependencies: 216
-- Name: COLUMN personal.id_sucursal; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.personal.id_sucursal IS 'ID de la sucursal a la que pertenece el personal';


--
-- TOC entry 215 (class 1259 OID 37604)
-- Name: personal_id_personal_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.personal_id_personal_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.personal_id_personal_seq OWNER TO postgres;

--
-- TOC entry 5701 (class 0 OID 0)
-- Dependencies: 215
-- Name: personal_id_personal_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.personal_id_personal_seq OWNED BY public.personal.id_personal;


--
-- TOC entry 263 (class 1259 OID 37820)
-- Name: presupuesto_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.presupuesto_compra (
    id_presupuesto_compra integer NOT NULL,
    presu_total integer NOT NULL,
    presu_fecha date NOT NULL,
    presu_estado character varying(20) NOT NULL,
    id_pedido_compra integer NOT NULL,
    id_usuario integer NOT NULL,
    id_sucursal integer NOT NULL,
    id_proveedor integer NOT NULL,
    descuento_total numeric(12,2) DEFAULT 0,
    presu_observaciones text,
    presu_ultima_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT presupuesto_compra_descuento_total_check CHECK ((descuento_total >= (0)::numeric))
);


ALTER TABLE public.presupuesto_compra OWNER TO postgres;

--
-- TOC entry 5702 (class 0 OID 0)
-- Dependencies: 263
-- Name: COLUMN presupuesto_compra.descuento_total; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.presupuesto_compra.descuento_total IS 'Descuento general aplicado al presupuesto (en la misma moneda que el total)';


--
-- TOC entry 5703 (class 0 OID 0)
-- Dependencies: 263
-- Name: COLUMN presupuesto_compra.presu_observaciones; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.presupuesto_compra.presu_observaciones IS 'Observaciones o notas adicionales del presupuesto';


--
-- TOC entry 5704 (class 0 OID 0)
-- Dependencies: 263
-- Name: COLUMN presupuesto_compra.presu_ultima_modificacion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.presupuesto_compra.presu_ultima_modificacion IS 'Timestamp de última modificación para control de concurrencia';


--
-- TOC entry 262 (class 1259 OID 37819)
-- Name: presupuesto_compra_id_presupuesto_compra_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.presupuesto_compra_id_presupuesto_compra_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.presupuesto_compra_id_presupuesto_compra_seq OWNER TO postgres;

--
-- TOC entry 5705 (class 0 OID 0)
-- Dependencies: 262
-- Name: presupuesto_compra_id_presupuesto_compra_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.presupuesto_compra_id_presupuesto_compra_seq OWNED BY public.presupuesto_compra.id_presupuesto_compra;


--
-- TOC entry 315 (class 1259 OID 38136)
-- Name: presupuesto_detalle_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.presupuesto_detalle_compra (
    id_presupuesto_compra integer NOT NULL,
    id_materia_prima integer NOT NULL,
    detalle_presu_cantidad integer NOT NULL,
    detalle_presu_precio_compra integer NOT NULL,
    descuento numeric(12,2) DEFAULT 0,
    detalle_presu_iva numeric(12,2) DEFAULT 0,
    CONSTRAINT presupuesto_detalle_compra_descuento_check CHECK ((descuento >= (0)::numeric)),
    CONSTRAINT presupuesto_detalle_compra_detalle_presu_iva_check CHECK ((detalle_presu_iva >= (0)::numeric))
);


ALTER TABLE public.presupuesto_detalle_compra OWNER TO postgres;

--
-- TOC entry 5706 (class 0 OID 0)
-- Dependencies: 315
-- Name: COLUMN presupuesto_detalle_compra.descuento; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.presupuesto_detalle_compra.descuento IS 'Descuento aplicado al ítem (en la misma moneda que el precio)';


--
-- TOC entry 5707 (class 0 OID 0)
-- Dependencies: 315
-- Name: COLUMN presupuesto_detalle_compra.detalle_presu_iva; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.presupuesto_detalle_compra.detalle_presu_iva IS 'Monto de IVA calculado para el ítem';


--
-- TOC entry 269 (class 1259 OID 37848)
-- Name: producto_terminado; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.producto_terminado (
    terminado_id integer NOT NULL,
    orden_id integer NOT NULL,
    terminado_fecha date NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.producto_terminado OWNER TO postgres;

--
-- TOC entry 268 (class 1259 OID 37847)
-- Name: producto_terminado_terminado_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.producto_terminado_terminado_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.producto_terminado_terminado_id_seq OWNER TO postgres;

--
-- TOC entry 5708 (class 0 OID 0)
-- Dependencies: 268
-- Name: producto_terminado_terminado_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.producto_terminado_terminado_id_seq OWNED BY public.producto_terminado.terminado_id;


--
-- TOC entry 291 (class 1259 OID 37997)
-- Name: productos; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.productos (
    producto_id integer NOT NULL,
    producto_precio integer NOT NULL,
    producto_descri character varying(30) NOT NULL,
    id_unidad integer NOT NULL,
    id_usuario integer NOT NULL,
    iva_id integer,
    producto_estado character varying(30) DEFAULT 'ACTIVO'::character varying,
    id_tipo_producto integer
);


ALTER TABLE public.productos OWNER TO postgres;

--
-- TOC entry 5709 (class 0 OID 0)
-- Dependencies: 291
-- Name: COLUMN productos.iva_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.productos.iva_id IS 'Tipo de IVA aplicable (5%, 10%, exento)';


--
-- TOC entry 5710 (class 0 OID 0)
-- Dependencies: 291
-- Name: COLUMN productos.producto_estado; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.productos.producto_estado IS 'Estado del producto: ACTIVO, INACTIVO, ANULADO';


--
-- TOC entry 5711 (class 0 OID 0)
-- Dependencies: 291
-- Name: COLUMN productos.id_tipo_producto; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.productos.id_tipo_producto IS 'Tipo de producto (opcional)';


--
-- TOC entry 290 (class 1259 OID 37996)
-- Name: productos_producto_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.productos_producto_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.productos_producto_id_seq OWNER TO postgres;

--
-- TOC entry 5712 (class 0 OID 0)
-- Dependencies: 290
-- Name: productos_producto_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.productos_producto_id_seq OWNED BY public.productos.producto_id;


--
-- TOC entry 294 (class 1259 OID 38013)
-- Name: productos_terminados_detalle; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.productos_terminados_detalle (
    terminado_id integer NOT NULL,
    producto_id integer NOT NULL,
    terminado_cantidad integer NOT NULL,
    deposito_id integer,
    terminado_fecha_elab date,
    terminado_fecha_venc date
);


ALTER TABLE public.productos_terminados_detalle OWNER TO postgres;

--
-- TOC entry 243 (class 1259 OID 37736)
-- Name: proveedor; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.proveedor (
    id_proveedor integer NOT NULL,
    id_usuario integer NOT NULL,
    razon_social character varying(150) NOT NULL,
    ruc_proveedor character varying(20) NOT NULL,
    telefono_proveedor character varying(30),
    direccion_proveedor character varying(200),
    email_proveedor character varying(120),
    estado_proveedor character varying(10) DEFAULT 'ACTIVO'::character varying NOT NULL
);


ALTER TABLE public.proveedor OWNER TO postgres;

--
-- TOC entry 242 (class 1259 OID 37735)
-- Name: proveedor_id_proveedor_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.proveedor_id_proveedor_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.proveedor_id_proveedor_seq OWNER TO postgres;

--
-- TOC entry 5713 (class 0 OID 0)
-- Dependencies: 242
-- Name: proveedor_id_proveedor_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.proveedor_id_proveedor_seq OWNED BY public.proveedor.id_proveedor;


--
-- TOC entry 261 (class 1259 OID 37813)
-- Name: reposicion_materia; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.reposicion_materia (
    reposicion_id integer NOT NULL,
    reposicion_fecha date NOT NULL,
    reposicion_estado character varying(30) NOT NULL,
    deposito_id integer NOT NULL,
    id_usuario integer NOT NULL,
    id_pedido_mat_prod integer,
    CONSTRAINT reposicion_materia_estado_chk CHECK (((reposicion_estado)::text = ANY ((ARRAY['REGISTRADO'::character varying, 'ANULADO'::character varying])::text[])))
);


ALTER TABLE public.reposicion_materia OWNER TO postgres;

--
-- TOC entry 5714 (class 0 OID 0)
-- Dependencies: 261
-- Name: COLUMN reposicion_materia.id_pedido_mat_prod; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.reposicion_materia.id_pedido_mat_prod IS 'Pedido de materia prima que se está atendiendo con esta reposición.';


--
-- TOC entry 305 (class 1259 OID 38083)
-- Name: reposicion_materia_detalle; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.reposicion_materia_detalle (
    reposicion_id integer NOT NULL,
    id_materia_prima integer NOT NULL,
    reposicion_cantidad integer NOT NULL
);


ALTER TABLE public.reposicion_materia_detalle OWNER TO postgres;

--
-- TOC entry 260 (class 1259 OID 37812)
-- Name: reposicion_materia_reposicion_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.reposicion_materia_reposicion_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.reposicion_materia_reposicion_id_seq OWNER TO postgres;

--
-- TOC entry 5715 (class 0 OID 0)
-- Dependencies: 260
-- Name: reposicion_materia_reposicion_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.reposicion_materia_reposicion_id_seq OWNED BY public.reposicion_materia.reposicion_id;


--
-- TOC entry 312 (class 1259 OID 38120)
-- Name: stock_materia_prima; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.stock_materia_prima (
    id_stock integer NOT NULL,
    id_materia_prima integer NOT NULL,
    stock_cantidad_minima integer NOT NULL,
    stock_cantidad_maxima integer NOT NULL,
    cantidad_existente integer NOT NULL,
    deposito_id integer NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.stock_materia_prima OWNER TO postgres;

--
-- TOC entry 311 (class 1259 OID 38119)
-- Name: stock_materia_prima_id_stock_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.stock_materia_prima_id_stock_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.stock_materia_prima_id_stock_seq OWNER TO postgres;

--
-- TOC entry 5716 (class 0 OID 0)
-- Dependencies: 311
-- Name: stock_materia_prima_id_stock_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.stock_materia_prima_id_stock_seq OWNED BY public.stock_materia_prima.id_stock;


--
-- TOC entry 301 (class 1259 OID 38065)
-- Name: stock_producto; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.stock_producto (
    id_stock_productos integer NOT NULL,
    producto_id integer NOT NULL,
    deposito_id integer NOT NULL,
    stock_prod_ven date NOT NULL,
    stock_prod_existente integer NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.stock_producto OWNER TO postgres;

--
-- TOC entry 300 (class 1259 OID 38064)
-- Name: stock_producto_id_stock_productos_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.stock_producto_id_stock_productos_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.stock_producto_id_stock_productos_seq OWNER TO postgres;

--
-- TOC entry 5717 (class 0 OID 0)
-- Dependencies: 300
-- Name: stock_producto_id_stock_productos_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.stock_producto_id_stock_productos_seq OWNED BY public.stock_producto.id_stock_productos;


--
-- TOC entry 247 (class 1259 OID 37750)
-- Name: sucursales; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sucursales (
    id_sucursal integer NOT NULL,
    descripcion_sucursal character varying(30) NOT NULL,
    estado_sucursal character varying(30) NOT NULL,
    id_usuario integer
);


ALTER TABLE public.sucursales OWNER TO postgres;

--
-- TOC entry 246 (class 1259 OID 37749)
-- Name: sucursales_id_sucursal_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sucursales_id_sucursal_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sucursales_id_sucursal_seq OWNER TO postgres;

--
-- TOC entry 5718 (class 0 OID 0)
-- Dependencies: 246
-- Name: sucursales_id_sucursal_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sucursales_id_sucursal_seq OWNED BY public.sucursales.id_sucursal;


--
-- TOC entry 249 (class 1259 OID 37757)
-- Name: timbrado; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.timbrado (
    id_timbrado integer NOT NULL,
    timbrado_numero integer NOT NULL,
    timbrado_fecha_inicio date NOT NULL,
    timbrado_fecha_fin date NOT NULL,
    timbrado_estado character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.timbrado OWNER TO postgres;

--
-- TOC entry 248 (class 1259 OID 37756)
-- Name: timbrado_id_timbrado_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.timbrado_id_timbrado_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.timbrado_id_timbrado_seq OWNER TO postgres;

--
-- TOC entry 5719 (class 0 OID 0)
-- Dependencies: 248
-- Name: timbrado_id_timbrado_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.timbrado_id_timbrado_seq OWNED BY public.timbrado.id_timbrado;


--
-- TOC entry 245 (class 1259 OID 37743)
-- Name: tipo_documento; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tipo_documento (
    id_tipo_documento integer NOT NULL,
    descripcion_tipo_documento character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.tipo_documento OWNER TO postgres;

--
-- TOC entry 244 (class 1259 OID 37742)
-- Name: tipo_documento_id_tipo_documento_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tipo_documento_id_tipo_documento_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tipo_documento_id_tipo_documento_seq OWNER TO postgres;

--
-- TOC entry 5720 (class 0 OID 0)
-- Dependencies: 244
-- Name: tipo_documento_id_tipo_documento_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tipo_documento_id_tipo_documento_seq OWNED BY public.tipo_documento.id_tipo_documento;


--
-- TOC entry 224 (class 1259 OID 37633)
-- Name: tipo_iva; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tipo_iva (
    iva_id integer NOT NULL,
    iva_descri character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.tipo_iva OWNER TO postgres;

--
-- TOC entry 223 (class 1259 OID 37632)
-- Name: tipo_iva_iva_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tipo_iva_iva_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tipo_iva_iva_id_seq OWNER TO postgres;

--
-- TOC entry 5721 (class 0 OID 0)
-- Dependencies: 223
-- Name: tipo_iva_iva_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tipo_iva_iva_id_seq OWNED BY public.tipo_iva.iva_id;


--
-- TOC entry 277 (class 1259 OID 37911)
-- Name: tipo_operacion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tipo_operacion (
    id_tipo_operacion integer NOT NULL,
    descri_tipo_operacion character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.tipo_operacion OWNER TO postgres;

--
-- TOC entry 276 (class 1259 OID 37910)
-- Name: tipo_operacion_id_tipo_operacion_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tipo_operacion_id_tipo_operacion_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tipo_operacion_id_tipo_operacion_seq OWNER TO postgres;

--
-- TOC entry 5722 (class 0 OID 0)
-- Dependencies: 276
-- Name: tipo_operacion_id_tipo_operacion_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tipo_operacion_id_tipo_operacion_seq OWNED BY public.tipo_operacion.id_tipo_operacion;


--
-- TOC entry 321 (class 1259 OID 65988)
-- Name: tipo_pedido; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tipo_pedido (
    id_tipo_pedido integer NOT NULL,
    tipo_pedido_descri character varying(50) NOT NULL,
    tipo_pedido_estado character varying(30) DEFAULT 'ACTIVO'::character varying NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.tipo_pedido OWNER TO postgres;

--
-- TOC entry 322 (class 1259 OID 65994)
-- Name: tipo_pedido_id_tipo_pedido_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tipo_pedido_id_tipo_pedido_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tipo_pedido_id_tipo_pedido_seq OWNER TO postgres;

--
-- TOC entry 5723 (class 0 OID 0)
-- Dependencies: 322
-- Name: tipo_pedido_id_tipo_pedido_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tipo_pedido_id_tipo_pedido_seq OWNED BY public.tipo_pedido.id_tipo_pedido;


--
-- TOC entry 226 (class 1259 OID 37661)
-- Name: tipo_perdida; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tipo_perdida (
    tipo_perdida_id integer NOT NULL,
    tipo_perdida_descri character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.tipo_perdida OWNER TO postgres;

--
-- TOC entry 225 (class 1259 OID 37660)
-- Name: tipo_perdida_tipo_perdida_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tipo_perdida_tipo_perdida_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tipo_perdida_tipo_perdida_id_seq OWNER TO postgres;

--
-- TOC entry 5724 (class 0 OID 0)
-- Dependencies: 225
-- Name: tipo_perdida_tipo_perdida_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tipo_perdida_tipo_perdida_id_seq OWNED BY public.tipo_perdida.tipo_perdida_id;


--
-- TOC entry 320 (class 1259 OID 44133)
-- Name: tipo_producto; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tipo_producto (
    cod_tipo_prod integer NOT NULL,
    t_p_descrip character varying(50) NOT NULL
);


ALTER TABLE public.tipo_producto OWNER TO postgres;

--
-- TOC entry 5725 (class 0 OID 0)
-- Dependencies: 320
-- Name: TABLE tipo_producto; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.tipo_producto IS 'Tipos de productos para clasificación';


--
-- TOC entry 5726 (class 0 OID 0)
-- Dependencies: 320
-- Name: COLUMN tipo_producto.cod_tipo_prod; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tipo_producto.cod_tipo_prod IS 'Código único del tipo de producto';


--
-- TOC entry 5727 (class 0 OID 0)
-- Dependencies: 320
-- Name: COLUMN tipo_producto.t_p_descrip; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tipo_producto.t_p_descrip IS 'Descripción del tipo de producto';


--
-- TOC entry 238 (class 1259 OID 37710)
-- Name: trabajadores; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.trabajadores (
    trabajadores_id integer NOT NULL,
    id_usuario integer NOT NULL,
    id_personal integer NOT NULL,
    trabajador_estado character varying(30) NOT NULL,
    trabajador_rol character varying(50),
    trabajador_turno character varying(30),
    trabajador_costo_hora numeric(12,2) DEFAULT 0 NOT NULL,
    id_etapa integer,
    CONSTRAINT trabajadores_costo_chk CHECK ((trabajador_costo_hora >= (0)::numeric)),
    CONSTRAINT trabajadores_turno_chk CHECK (((trabajador_turno IS NULL) OR ((trabajador_turno)::text = ANY ((ARRAY['MAÑANA'::character varying, 'TARDE'::character varying, 'NOCHE'::character varying, 'ROTATIVO'::character varying])::text[]))))
);


ALTER TABLE public.trabajadores OWNER TO postgres;

--
-- TOC entry 5728 (class 0 OID 0)
-- Dependencies: 238
-- Name: COLUMN trabajadores.trabajador_costo_hora; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.trabajadores.trabajador_costo_hora IS 'Costo hora para cálculo de mano de obra en costos de producción.';


--
-- TOC entry 237 (class 1259 OID 37709)
-- Name: trabajadores_trabajadores_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.trabajadores_trabajadores_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.trabajadores_trabajadores_id_seq OWNER TO postgres;

--
-- TOC entry 5729 (class 0 OID 0)
-- Dependencies: 237
-- Name: trabajadores_trabajadores_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.trabajadores_trabajadores_id_seq OWNED BY public.trabajadores.trabajadores_id;


--
-- TOC entry 289 (class 1259 OID 37990)
-- Name: unidad_medida; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.unidad_medida (
    id_unidad integer NOT NULL,
    unidad_descri character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.unidad_medida OWNER TO postgres;

--
-- TOC entry 288 (class 1259 OID 37989)
-- Name: unidad_medida_id_unidad_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.unidad_medida_id_unidad_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.unidad_medida_id_unidad_seq OWNER TO postgres;

--
-- TOC entry 5730 (class 0 OID 0)
-- Dependencies: 288
-- Name: unidad_medida_id_unidad_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.unidad_medida_id_unidad_seq OWNED BY public.unidad_medida.id_unidad;


--
-- TOC entry 218 (class 1259 OID 37612)
-- Name: usuarios; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.usuarios (
    id_usuario integer NOT NULL,
    estado_usuario character varying(30) NOT NULL,
    id_sucursal integer NOT NULL,
    modulo_id integer NOT NULL,
    username character varying(30) NOT NULL,
    usua_password character varying(100) NOT NULL,
    id_cargo integer,
    id_personal integer
);


ALTER TABLE public.usuarios OWNER TO postgres;

--
-- TOC entry 5731 (class 0 OID 0)
-- Dependencies: 218
-- Name: COLUMN usuarios.id_personal; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.usuarios.id_personal IS 'Referencia opcional al registro de personal asociado al usuario';


--
-- TOC entry 217 (class 1259 OID 37611)
-- Name: usuarios_id_usuario_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.usuarios_id_usuario_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.usuarios_id_usuario_seq OWNER TO postgres;

--
-- TOC entry 5732 (class 0 OID 0)
-- Dependencies: 217
-- Name: usuarios_id_usuario_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.usuarios_id_usuario_seq OWNED BY public.usuarios.id_usuario;


--
-- TOC entry 230 (class 1259 OID 37675)
-- Name: vehiculos; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.vehiculos (
    vehiculo_id integer NOT NULL,
    vehiculo_marca character varying(30) NOT NULL,
    vehiculo_ano character varying(30) NOT NULL,
    vehiculo_color character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.vehiculos OWNER TO postgres;

--
-- TOC entry 229 (class 1259 OID 37674)
-- Name: vehiculos_vehiculo_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.vehiculos_vehiculo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.vehiculos_vehiculo_id_seq OWNER TO postgres;

--
-- TOC entry 5733 (class 0 OID 0)
-- Dependencies: 229
-- Name: vehiculos_vehiculo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.vehiculos_vehiculo_id_seq OWNED BY public.vehiculos.vehiculo_id;


--
-- TOC entry 5021 (class 2604 OID 37779)
-- Name: ajustes id_ajuste; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes ALTER COLUMN id_ajuste SET DEFAULT nextval('public.ajuste_stock_id_ajuste_seq'::regclass);


--
-- TOC entry 5060 (class 2604 OID 39044)
-- Name: bitacora id_bitacora; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bitacora ALTER COLUMN id_bitacora SET DEFAULT nextval('public.bitacora_id_bitacora_seq'::regclass);


--
-- TOC entry 5020 (class 2604 OID 37772)
-- Name: cargos id_cargo; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cargos ALTER COLUMN id_cargo SET DEFAULT nextval('public.cargos_id_cargo_seq'::regclass);


--
-- TOC entry 5008 (class 2604 OID 37685)
-- Name: conductores conductor_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.conductores ALTER COLUMN conductor_id SET DEFAULT nextval('public.conductores_conductor_id_seq'::regclass);


--
-- TOC entry 5036 (class 2604 OID 37858)
-- Name: control_calidad_produccion calidad_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_produccion ALTER COLUMN calidad_id SET DEFAULT nextval('public.control_calidad_produccion_calidad_id_seq'::regclass);


--
-- TOC entry 5037 (class 2604 OID 37865)
-- Name: control_produccion control_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion ALTER COLUMN control_id SET DEFAULT nextval('public.control_produccion_control_id_seq'::regclass);


--
-- TOC entry 5054 (class 2604 OID 66114)
-- Name: costo_detalle_produccion costo_detalle_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_detalle_produccion ALTER COLUMN costo_detalle_id SET DEFAULT nextval('public.costo_detalle_produccion_costo_detalle_id_seq'::regclass);


--
-- TOC entry 5027 (class 2604 OID 37809)
-- Name: costo_produccion costo_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_produccion ALTER COLUMN costo_id SET DEFAULT nextval('public.costo_produccion_costo_id_seq'::regclass);


--
-- TOC entry 5022 (class 2604 OID 37786)
-- Name: cuentas_pagar id_cuenta_pagar; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cuentas_pagar ALTER COLUMN id_cuenta_pagar SET DEFAULT nextval('public.cuentas_pagar_id_cuenta_pagar_seq'::regclass);


--
-- TOC entry 5009 (class 2604 OID 37692)
-- Name: deposito deposito_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposito ALTER COLUMN deposito_id SET DEFAULT nextval('public.deposito_deposito_id_seq'::regclass);


--
-- TOC entry 5002 (class 2604 OID 37629)
-- Name: equipos_produccion equipo_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipos_produccion ALTER COLUMN equipo_id SET DEFAULT nextval('public.equipos_produccion_equipo_id_seq'::regclass);


--
-- TOC entry 5000 (class 2604 OID 37622)
-- Name: etapa_produccion etapa_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.etapa_produccion ALTER COLUMN etapa_id SET DEFAULT nextval('public.etapa_produccion_etapa_id_seq'::regclass);


--
-- TOC entry 5040 (class 2604 OID 37921)
-- Name: factura_compra id_factura_compra; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_compra ALTER COLUMN id_factura_compra SET DEFAULT nextval('public.factura_compra_id_factura_compra_seq'::regclass);


--
-- TOC entry 5063 (class 2604 OID 44086)
-- Name: historial_productos id_historial; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historial_productos ALTER COLUMN id_historial SET DEFAULT nextval('public.historial_productos_id_historial_seq'::regclass);


--
-- TOC entry 5010 (class 2604 OID 37706)
-- Name: inspectores id_inspectores; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inspectores ALTER COLUMN id_inspectores SET DEFAULT nextval('public.inspectores_id_inspectores_seq'::regclass);


--
-- TOC entry 5041 (class 2604 OID 37928)
-- Name: iva_compra iva_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.iva_compra ALTER COLUMN iva_id SET DEFAULT nextval('public.iva_compra_id_libro_compra_seq'::regclass);


--
-- TOC entry 5053 (class 2604 OID 38075)
-- Name: materia_prima id_materia_prima; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.materia_prima ALTER COLUMN id_materia_prima SET DEFAULT nextval('public.materia_prima_id_materia_prima_seq'::regclass);


--
-- TOC entry 5014 (class 2604 OID 37725)
-- Name: modulos modulo_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.modulos ALTER COLUMN modulo_id SET DEFAULT nextval('public.modulos_modulo_id_seq'::regclass);


--
-- TOC entry 5043 (class 2604 OID 37972)
-- Name: motivo id_motivo; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.motivo ALTER COLUMN id_motivo SET DEFAULT nextval('public.motivo_id_motivo_seq'::regclass);


--
-- TOC entry 5045 (class 2604 OID 37986)
-- Name: nota_compra id_nota_compra; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_compra ALTER COLUMN id_nota_compra SET DEFAULT nextval('public.nota_compra_id_nota_compra_seq'::regclass);


--
-- TOC entry 5042 (class 2604 OID 37937)
-- Name: nota_remision_compra id_nota_remision; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra ALTER COLUMN id_nota_remision SET DEFAULT nextval('public.nota_remision_compra_id_nota_remision_seq'::regclass);


--
-- TOC entry 5032 (class 2604 OID 37830)
-- Name: orden_de_compra id_orden_compra; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_de_compra ALTER COLUMN id_orden_compra SET DEFAULT nextval('public.orden_de_compra_id_orden_compra_seq'::regclass);


--
-- TOC entry 5034 (class 2604 OID 37844)
-- Name: orden_produccion orden_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_produccion ALTER COLUMN orden_id SET DEFAULT nextval('public.orden_produccion_orden_id_seq'::regclass);


--
-- TOC entry 5050 (class 2604 OID 38056)
-- Name: parametros_control parametro_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.parametros_control ALTER COLUMN parametro_id SET DEFAULT nextval('public.parametros_control_parametro_id_seq'::regclass);


--
-- TOC entry 5006 (class 2604 OID 37671)
-- Name: pedido_materia_produccion id_pedido_mat_prod; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_produccion ALTER COLUMN id_pedido_mat_prod SET DEFAULT nextval('public.pedido_materia_produccion_id_pedido_mat_prod_seq'::regclass);


--
-- TOC entry 5067 (class 2604 OID 66011)
-- Name: pedido_produccion id_pedido_produccion; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_produccion ALTER COLUMN id_pedido_produccion SET DEFAULT nextval('public.pedido_produccion_id_pedido_produccion_seq'::regclass);


--
-- TOC entry 5025 (class 2604 OID 37802)
-- Name: pedidos_compra id_pedido_compra; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedidos_compra ALTER COLUMN id_pedido_compra SET DEFAULT nextval('public.pedidos_compra_id_pedido_compra_seq'::regclass);


--
-- TOC entry 5038 (class 2604 OID 37872)
-- Name: perdidas perdidas_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas ALTER COLUMN perdidas_id SET DEFAULT nextval('public.perdidas_perdidas_id_seq'::regclass);


--
-- TOC entry 4998 (class 2604 OID 37608)
-- Name: personal id_personal; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal ALTER COLUMN id_personal SET DEFAULT nextval('public.personal_id_personal_seq'::regclass);


--
-- TOC entry 5029 (class 2604 OID 37823)
-- Name: presupuesto_compra id_presupuesto_compra; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_compra ALTER COLUMN id_presupuesto_compra SET DEFAULT nextval('public.presupuesto_compra_id_presupuesto_compra_seq'::regclass);


--
-- TOC entry 5035 (class 2604 OID 37851)
-- Name: producto_terminado terminado_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.producto_terminado ALTER COLUMN terminado_id SET DEFAULT nextval('public.producto_terminado_terminado_id_seq'::regclass);


--
-- TOC entry 5047 (class 2604 OID 38000)
-- Name: productos producto_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos ALTER COLUMN producto_id SET DEFAULT nextval('public.productos_producto_id_seq'::regclass);


--
-- TOC entry 5015 (class 2604 OID 37739)
-- Name: proveedor id_proveedor; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.proveedor ALTER COLUMN id_proveedor SET DEFAULT nextval('public.proveedor_id_proveedor_seq'::regclass);


--
-- TOC entry 5028 (class 2604 OID 37816)
-- Name: reposicion_materia reposicion_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia ALTER COLUMN reposicion_id SET DEFAULT nextval('public.reposicion_materia_reposicion_id_seq'::regclass);


--
-- TOC entry 5057 (class 2604 OID 38123)
-- Name: stock_materia_prima id_stock; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_materia_prima ALTER COLUMN id_stock SET DEFAULT nextval('public.stock_materia_prima_id_stock_seq'::regclass);


--
-- TOC entry 5052 (class 2604 OID 38068)
-- Name: stock_producto id_stock_productos; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_producto ALTER COLUMN id_stock_productos SET DEFAULT nextval('public.stock_producto_id_stock_productos_seq'::regclass);


--
-- TOC entry 5018 (class 2604 OID 37753)
-- Name: sucursales id_sucursal; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sucursales ALTER COLUMN id_sucursal SET DEFAULT nextval('public.sucursales_id_sucursal_seq'::regclass);


--
-- TOC entry 5019 (class 2604 OID 37760)
-- Name: timbrado id_timbrado; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.timbrado ALTER COLUMN id_timbrado SET DEFAULT nextval('public.timbrado_id_timbrado_seq'::regclass);


--
-- TOC entry 5017 (class 2604 OID 37746)
-- Name: tipo_documento id_tipo_documento; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_documento ALTER COLUMN id_tipo_documento SET DEFAULT nextval('public.tipo_documento_id_tipo_documento_seq'::regclass);


--
-- TOC entry 5004 (class 2604 OID 37636)
-- Name: tipo_iva iva_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_iva ALTER COLUMN iva_id SET DEFAULT nextval('public.tipo_iva_iva_id_seq'::regclass);


--
-- TOC entry 5039 (class 2604 OID 37914)
-- Name: tipo_operacion id_tipo_operacion; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_operacion ALTER COLUMN id_tipo_operacion SET DEFAULT nextval('public.tipo_operacion_id_tipo_operacion_seq'::regclass);


--
-- TOC entry 5065 (class 2604 OID 65995)
-- Name: tipo_pedido id_tipo_pedido; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_pedido ALTER COLUMN id_tipo_pedido SET DEFAULT nextval('public.tipo_pedido_id_tipo_pedido_seq'::regclass);


--
-- TOC entry 5005 (class 2604 OID 37664)
-- Name: tipo_perdida tipo_perdida_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_perdida ALTER COLUMN tipo_perdida_id SET DEFAULT nextval('public.tipo_perdida_tipo_perdida_id_seq'::regclass);


--
-- TOC entry 5011 (class 2604 OID 37713)
-- Name: trabajadores trabajadores_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trabajadores ALTER COLUMN trabajadores_id SET DEFAULT nextval('public.trabajadores_trabajadores_id_seq'::regclass);


--
-- TOC entry 5046 (class 2604 OID 37993)
-- Name: unidad_medida id_unidad; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.unidad_medida ALTER COLUMN id_unidad SET DEFAULT nextval('public.unidad_medida_id_unidad_seq'::regclass);


--
-- TOC entry 4999 (class 2604 OID 37615)
-- Name: usuarios id_usuario; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios ALTER COLUMN id_usuario SET DEFAULT nextval('public.usuarios_id_usuario_seq'::regclass);


--
-- TOC entry 5007 (class 2604 OID 37678)
-- Name: vehiculos vehiculo_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.vehiculos ALTER COLUMN vehiculo_id SET DEFAULT nextval('public.vehiculos_vehiculo_id_seq'::regclass);


--
-- TOC entry 5571 (class 0 OID 37776)
-- Dependencies: 253
-- Data for Name: ajustes; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.ajustes (id_ajuste, ajuste_fecha, ajuste_estado, id_usuario, deposito_id) FROM stdin;
\.


--
-- TOC entry 5631 (class 0 OID 38126)
-- Dependencies: 313
-- Data for Name: ajustes_detalle; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.ajustes_detalle (id_materia_prima, id_ajuste, id_stock, ajuste_cantidad, id_motivo) FROM stdin;
\.


--
-- TOC entry 5635 (class 0 OID 39041)
-- Dependencies: 317
-- Data for Name: bitacora; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.bitacora (id_bitacora, id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio, fecha_accion, ip_origen, navegador_usuario, estado_registro) FROM stdin;
510	1	pedido compra	1	ALTA	Se inserta registro cabecera de Pedido Compra	2025-12-05 01:10:44.563387	\N	\N	ACTIVO
511	1	pedido compra	1	ALTA	Detalle agregado (pedido 1, materia prima 1, cant 100)	2025-12-05 01:10:44.563387	\N	\N	ACTIVO
512	1	pedido compra	1	MODIFICACION	[2025-12-05 12:54:43] Modifica cantidad del producto 1 en pedido 1: 100 → 50	2025-12-05 12:54:43.545474	\N	\N	ACTIVO
513	1	pedido compra	1	INACTIVACION	Se anula el Pedido de Compra #1	2025-12-05 12:55:00.146091	\N	\N	ACTIVO
514	1	pedido compra	2	ALTA	Se inserta registro cabecera de Pedido Compra	2025-12-05 12:55:20.153249	\N	\N	ACTIVO
515	1	pedido compra	2	ALTA	Detalle agregado (pedido 2, materia prima 2, cant 100)	2025-12-05 12:55:20.153249	\N	\N	ACTIVO
516	1	Presupuesto compra	13	ALTA	Presupuesto Compra: se crea cabecera #13 (UI Presupuesto: #1) por pedido 2. Total: 998000.	2025-12-05 12:56:10.089916	\N	\N	ACTIVO
517	1	Presupuesto compra	13	ALTA	Detalle Presupuesto Compra: presupuesto #13, materia prima: 2, cantidad: 100, precio 10000.	2025-12-05 12:56:10.089916	\N	\N	ACTIVO
518	1	Presupuesto compra	2	MODIFICACION	Update estado de Pedido Compra: pedido 2 → APROBADO por presupuesto #13.	2025-12-05 12:56:10.089916	\N	\N	ACTIVO
519	1	Presupuesto compra	13	MODIFICACION	[2025-12-05 16:56:30] Modifica DETALLE presu #13, prod 2: cant 100 → 100, precio 10000 → 20000, descuento 2000 → 2000, iva 47500 → 95100	2025-12-05 12:56:30.776285	\N	\N	ACTIVO
520	1	Presupuesto compra	13	MODIFICACION	[2025-12-05 16:56:30] MODIFICA CABECERA presu #13 | total=998000→1998000	2025-12-05 12:56:30.776285	\N	\N	ACTIVO
521	1	Presupuesto compra	13	INACTIVACION	[2025-12-05 12:58:15] Se ANULA el Presupuesto de Compra #13. Pedido #2 vuelve a PENDIENTE.	2025-12-05 12:58:15.771425	\N	\N	ACTIVO
522	1	Presupuesto compra	14	ALTA	Presupuesto Compra: se crea cabecera #14 (UI Presupuesto: #14) por pedido 2. Total: 500000.	2025-12-05 12:58:51.696468	\N	\N	ACTIVO
523	1	Presupuesto compra	14	ALTA	Detalle Presupuesto Compra: presupuesto #14, materia prima: 2, cantidad: 100, precio 5000.	2025-12-05 12:58:51.696468	\N	\N	ACTIVO
524	1	Presupuesto compra	2	MODIFICACION	Update estado de Pedido Compra: pedido 2 → APROBADO por presupuesto #14.	2025-12-05 12:58:51.696468	\N	\N	ACTIVO
525	1	Orden compra	1	ALTA	Crea Orden de Compra #1 (presupuesto 14, proveedor 9, condicion CONTADO)	2025-12-05 12:59:42.700423	\N	\N	ACTIVO
526	1	Orden compra	1	ALTA	Detalle Orden de Compra: orden #1, materia prima: 2, cantidad: 100, precio 5000.	2025-12-05 12:59:42.700423	\N	\N	ACTIVO
527	1	Orden compra	14	MODIFICACION	Actualiza presupuesto 14 a APROBADO  por generación de Orden #1	2025-12-05 12:59:42.700423	\N	\N	ACTIVO
528	1	Orden compra	1	MODIFICACION	Actualiza Orden de Compra #1: condición  → CONTADO	2025-12-05 13:00:44.83421	\N	\N	ACTIVO
529	1	pedido compra	3	ALTA	Se inserta registro cabecera de Pedido Compra	2025-12-05 13:01:07.280972	\N	\N	ACTIVO
530	1	pedido compra	3	ALTA	Detalle agregado (pedido 3, materia prima 3, cant 10)	2025-12-05 13:01:07.280972	\N	\N	ACTIVO
531	1	Presupuesto compra	15	ALTA	Presupuesto Compra: se crea cabecera #15 (UI Presupuesto: #15) por pedido 3. Total: 100000.	2025-12-05 13:01:32.512566	\N	\N	ACTIVO
532	1	Presupuesto compra	15	ALTA	Detalle Presupuesto Compra: presupuesto #15, materia prima: 3, cantidad: 10, precio 10000.	2025-12-05 13:01:32.512566	\N	\N	ACTIVO
533	1	Presupuesto compra	3	MODIFICACION	Update estado de Pedido Compra: pedido 3 → APROBADO por presupuesto #15.	2025-12-05 13:01:32.512566	\N	\N	ACTIVO
534	1	Orden compra	2	ALTA	Crea Orden de Compra #2 (presupuesto 15, proveedor 9, condicion CREDITO)	2025-12-05 13:01:46.483442	\N	\N	ACTIVO
535	1	Orden compra	2	ALTA	Detalle Orden de Compra: orden #2, materia prima: 3, cantidad: 10, precio 10000.	2025-12-05 13:01:46.483442	\N	\N	ACTIVO
536	1	Orden compra	15	MODIFICACION	Actualiza presupuesto 15 a APROBADO  por generación de Orden #2	2025-12-05 13:01:46.483442	\N	\N	ACTIVO
537	1	Gestionar compra / Factura	18	ALTA	Factura #18 | OC:1 | Nro:001-002-0011111 | Timbrado:12345678 | Emisión:2025-12-05 | Total:500000 | Tipo:CONTADO | Cuotas:0 | %Int:0	2025-12-05 13:03:14.288991	\N	\N	ACTIVO
538	1	Gestionar compra / Factura	1	MODIFICACION	OC #1 → FACTURADA (Factura #18)	2025-12-05 13:03:14.288991	\N	\N	ACTIVO
539	1	Gestionar compra / Factura	18	ALTA	Detalle Factura #18 | Materia Prima:2 | Cant:100 | Precio:5000 | IVA:23809	2025-12-05 13:03:14.288991	\N	\N	ACTIVO
540	1	Gestionar compra / Factura	18	ALTA	IVA COMPRA Factura #18 | Exento:0 | IVA5:23809 | IVA10:0	2025-12-05 13:03:14.288991	\N	\N	ACTIVO
541	1	Gestionar compra / Factura	18	MODIFICACION	Stock actualizado: +100 unid. | Materia Prima:2 | Depósito:1 | Factura #18	2025-12-05 13:03:14.288991	\N	\N	ACTIVO
542	1	Gestionar compra / Factura	11	ALTA	CtaPagar #11 | Factura #18 | Total:500000 | Estado:PENDIENTE	2025-12-05 13:03:14.288991	\N	\N	ACTIVO
543	1	Gestionar compra / Factura	14	MODIFICACION	Presupuesto #14 → FINALIZADO (Factura #18)	2025-12-05 13:03:14.288991	\N	\N	ACTIVO
544	1	Gestionar compra / Factura	2	MODIFICACION	Pedido #2 → FINALIZADO (Factura #18)	2025-12-05 13:03:14.288991	\N	\N	ACTIVO
545	1	Gestionar compra / Factura	19	ALTA	Factura #19 | OC:2 | Nro:001-002-0011112 | Timbrado:23424242 | Emisión:2025-12-05 | Total:100000 | Tipo:CREDITO | Cuotas:10 | %Int:10	2025-12-05 13:07:19.283261	\N	\N	ACTIVO
546	1	Gestionar compra / Factura	2	MODIFICACION	OC #2 → FACTURADA (Factura #19)	2025-12-05 13:07:19.283261	\N	\N	ACTIVO
547	1	Gestionar compra / Factura	19	ALTA	Detalle Factura #19 | Materia Prima:3 | Cant:10 | Precio:10000 | IVA:4761	2025-12-05 13:07:19.283261	\N	\N	ACTIVO
548	1	Gestionar compra / Factura	19	ALTA	IVA COMPRA Factura #19 | Exento:0 | IVA5:4761 | IVA10:0	2025-12-05 13:07:19.283261	\N	\N	ACTIVO
549	1	Gestionar compra / Factura	19	MODIFICACION	Stock actualizado: +10 unid. | Materia Prima:3 | Depósito:1 | Factura #19	2025-12-05 13:07:19.283261	\N	\N	ACTIVO
550	1	Gestionar compra / Factura	12	ALTA	CtaPagar #12 | Factura #19 | Total:110000 | Estado:PENDIENTE	2025-12-05 13:07:19.283261	\N	\N	ACTIVO
551	1	Gestionar compra / Factura	15	MODIFICACION	Presupuesto #15 → FINALIZADO (Factura #19)	2025-12-05 13:07:19.283261	\N	\N	ACTIVO
552	1	Gestionar compra / Factura	3	MODIFICACION	Pedido #3 → FINALIZADO (Factura #19)	2025-12-05 13:07:19.283261	\N	\N	ACTIVO
555	1	Gestionar compra / Factura	18	MODIFICACION	Stock revertido: -100 unid. | Materia Prima:2 | Depósito:1 | Factura #18	2025-12-05 13:08:49.510599	\N	\N	ACTIVO
556	1	Gestionar compra / Factura	1	MODIFICACION	OC #1 → EMITIDA (Anulación Factura #18)	2025-12-05 13:08:49.510599	\N	\N	ACTIVO
557	1	Gestionar compra / Factura	19	ALTA	IVA COMPRA Factura #19 | Exento:0 | IVA5:4761 | IVA10:0	2025-12-05 13:09:43.380001	\N	\N	ACTIVO
558	1	Gestionar compra / Factura	2	MODIFICACION	OC #2 → FINALIZADO (Factura #19)	2025-12-05 13:09:43.380001	\N	\N	ACTIVO
559	1	Gestionar compra / Factura	15	MODIFICACION	Presupuesto #15 → FINALIZADO (Factura #19)	2025-12-05 13:09:43.380001	\N	\N	ACTIVO
560	1	Gestionar compra / Factura	3	MODIFICACION	Pedido #3 → FINALIZADO (Factura #19)	2025-12-05 13:09:43.380001	\N	\N	ACTIVO
561	1	Gestionar compra / Factura	19	ALTA	UPDATE stock sin NR: +10 unid. | Materia Prima:3 | Depósito:1 | Factura #19	2025-12-05 13:09:43.380001	\N	\N	ACTIVO
562	1	Gestionar compra / Factura	19	MODIFICACION	Factura #19 aprobada | Estado: EMITIDA | CtaPagar: PENDIENTE | Tipo: CREDITO	2025-12-05 13:09:43.380001	\N	\N	ACTIVO
563	1	pedido compra	1	ALTA	Se inserta registro cabecera de Pedido Compra	2025-12-05 13:25:25.611228	\N	\N	ACTIVO
564	1	pedido compra	1	ALTA	Detalle agregado (pedido 1, materia prima 1, cant 10)	2025-12-05 13:25:25.611228	\N	\N	ACTIVO
565	1	Presupuesto compra	16	ALTA	Presupuesto Compra: se crea cabecera #16 (UI Presupuesto: #1) por pedido 1. Total: 100000.	2025-12-05 13:25:40.349719	\N	\N	ACTIVO
566	1	Presupuesto compra	16	ALTA	Detalle Presupuesto Compra: presupuesto #16, materia prima: 1, cantidad: 10, precio 10000.	2025-12-05 13:25:40.349719	\N	\N	ACTIVO
567	1	Presupuesto compra	1	MODIFICACION	Update estado de Pedido Compra: pedido 1 → APROBADO por presupuesto #16.	2025-12-05 13:25:40.349719	\N	\N	ACTIVO
568	1	Orden compra	1	ALTA	Crea Orden de Compra #1 (presupuesto 16, proveedor 9, condicion CONTADO)	2025-12-05 13:25:49.929711	\N	\N	ACTIVO
569	1	Orden compra	1	ALTA	Detalle Orden de Compra: orden #1, materia prima: 1, cantidad: 10, precio 10000.	2025-12-05 13:25:49.929711	\N	\N	ACTIVO
570	1	Orden compra	16	MODIFICACION	Actualiza presupuesto 16 a APROBADO  por generación de Orden #1	2025-12-05 13:25:49.929711	\N	\N	ACTIVO
571	1	Gestionar compra / Factura	20	ALTA	Factura #20 | OC:1 | Nro:001-002-0011111 | Timbrado:15698412 | Emisión:2025-12-05 | Total:100000 | Tipo:CONTADO | Cuotas:0 | %Int:0	2025-12-05 13:26:03.826605	\N	\N	ACTIVO
572	1	Gestionar compra / Factura	1	MODIFICACION	OC #1 → FACTURADA (Factura #20)	2025-12-05 13:26:03.826605	\N	\N	ACTIVO
573	1	Gestionar compra / Factura	20	ALTA	Detalle Factura #20 | Materia Prima:1 | Cant:10 | Precio:10000 | IVA:4761	2025-12-05 13:26:03.826605	\N	\N	ACTIVO
574	1	Gestionar compra / Factura	20	ALTA	IVA COMPRA Factura #20 | Exento:0 | IVA5:4761 | IVA10:0	2025-12-05 13:26:03.826605	\N	\N	ACTIVO
575	1	Gestionar compra / Factura	20	MODIFICACION	Stock actualizado: +10 unid. | Materia Prima:1 | Depósito:1 | Factura #20	2025-12-05 13:26:03.826605	\N	\N	ACTIVO
576	1	Gestionar compra / Factura	13	ALTA	CtaPagar #13 | Factura #20 | Total:100000 | Estado:PENDIENTE	2025-12-05 13:26:03.826605	\N	\N	ACTIVO
577	1	Gestionar compra / Factura	16	MODIFICACION	Presupuesto #16 → FINALIZADO (Factura #20)	2025-12-05 13:26:03.826605	\N	\N	ACTIVO
578	1	Gestionar compra / Factura	1	MODIFICACION	Pedido #1 → FINALIZADO (Factura #20)	2025-12-05 13:26:03.826605	\N	\N	ACTIVO
581	1	Gestionar compra / Factura	20	MODIFICACION	Stock revertido: -10 unid. | Materia Prima:1 | Depósito:1 | Factura #20	2025-12-05 13:30:43.874213	\N	\N	ACTIVO
582	1	Gestionar compra / Factura	1	MODIFICACION	OC #1 → EMITIDA (Anulación Factura #20)	2025-12-05 13:30:43.874213	\N	\N	ACTIVO
583	1	pedido compra	2	ALTA	Se inserta registro cabecera de Pedido Compra	2025-12-05 13:42:00.181162	\N	\N	ACTIVO
584	1	pedido compra	1	ALTA	Detalle agregado (pedido 2, materia prima 1, cant 10)	2025-12-05 13:42:00.181162	\N	\N	ACTIVO
585	1	Orden compra	1	INACTIVACION	Anula Orden de Compra #1	2025-12-05 13:45:33.730097	\N	\N	ACTIVO
586	1	Orden compra	16	MODIFICACION	Revierte presupuesto 16 a EMITIDO (por anulación de OC #1)	2025-12-05 13:45:33.730097	\N	\N	ACTIVO
587	1	Orden compra	2	ALTA	Crea Orden de Compra #2 (presupuesto 16, proveedor 9, condicion CREDITO)	2025-12-05 13:46:06.07449	\N	\N	ACTIVO
588	1	Orden compra	2	ALTA	Detalle Orden de Compra: orden #2, materia prima: 1, cantidad: 10, precio 10000.	2025-12-05 13:46:06.07449	\N	\N	ACTIVO
589	1	Orden compra	16	MODIFICACION	Actualiza presupuesto 16 a APROBADO  por generación de Orden #2	2025-12-05 13:46:06.07449	\N	\N	ACTIVO
590	1	Gestionar compra / Factura	21	ALTA	Factura #21 | OC:2 | Nro:001-002-0011112 | Timbrado:15698412 | Emisión:2025-12-05 | Total:100000 | Tipo:CREDITO | Cuotas:10 | %Int:10	2025-12-05 13:46:26.245046	\N	\N	ACTIVO
591	1	Gestionar compra / Factura	2	MODIFICACION	OC #2 → FACTURADA (Factura #21)	2025-12-05 13:46:26.245046	\N	\N	ACTIVO
592	1	Gestionar compra / Factura	21	ALTA	Detalle Factura #21 | Materia Prima:1 | Cant:10 | Precio:10000 | IVA:4761	2025-12-05 13:46:26.245046	\N	\N	ACTIVO
593	1	Gestionar compra / Factura	21	ALTA	IVA COMPRA Factura #21 | Exento:0 | IVA5:4761 | IVA10:0	2025-12-05 13:46:26.245046	\N	\N	ACTIVO
594	1	Gestionar compra / Factura	21	MODIFICACION	Stock actualizado: +10 unid. | Materia Prima:1 | Depósito:1 | Factura #21	2025-12-05 13:46:26.245046	\N	\N	ACTIVO
595	1	Gestionar compra / Factura	14	ALTA	CtaPagar #14 | Factura #21 | Total:110000 | Estado:PENDIENTE	2025-12-05 13:46:26.245046	\N	\N	ACTIVO
596	1	Gestionar compra / Factura	16	MODIFICACION	Presupuesto #16 → FINALIZADO (Factura #21)	2025-12-05 13:46:26.245046	\N	\N	ACTIVO
597	1	Gestionar compra / Factura	1	MODIFICACION	Pedido #1 → FINALIZADO (Factura #21)	2025-12-05 13:46:26.245046	\N	\N	ACTIVO
598	1	Gestionar compra / Factura	21	ALTA	IVA COMPRA Factura #21 | Exento:0 | IVA5:4761 | IVA10:0	2025-12-05 13:46:52.130157	\N	\N	ACTIVO
599	1	Gestionar compra / Factura	2	MODIFICACION	OC #2 → FINALIZADO (Factura #21)	2025-12-05 13:46:52.130157	\N	\N	ACTIVO
600	1	Gestionar compra / Factura	16	MODIFICACION	Presupuesto #16 → FINALIZADO (Factura #21)	2025-12-05 13:46:52.130157	\N	\N	ACTIVO
601	1	Gestionar compra / Factura	1	MODIFICACION	Pedido #1 → FINALIZADO (Factura #21)	2025-12-05 13:46:52.130157	\N	\N	ACTIVO
602	1	Gestionar compra / Factura	21	ALTA	UPDATE stock sin NR: +10 unid. | Materia Prima:1 | Depósito:1 | Factura #21	2025-12-05 13:46:52.130157	\N	\N	ACTIVO
603	1	Gestionar compra / Factura	21	MODIFICACION	Factura #21 aprobada | Estado: EMITIDA | CtaPagar: PENDIENTE | Tipo: CREDITO	2025-12-05 13:46:52.130157	\N	\N	ACTIVO
604	1	pedido venta	1	ALTA	Se inserta registro cabecera de Pedido Venta #1	2025-12-05 14:00:35.318827	\N	\N	ACTIVO
605	1	pedido venta	1	ALTA	Detalle agregado (pedido 1, producto 1, cantidad 5)	2025-12-05 14:00:35.318827	\N	\N	ACTIVO
606	1	pedido venta	1	MODIFICACION	[2025-12-05 14:07:46] Modifica cantidad del producto 1 en pedido 1: 5 → 10	2025-12-05 14:07:46.772113	\N	\N	ACTIVO
607	1	pedido venta	1	INACTIVACION	Se anula el Pedido de Venta #1	2025-12-05 14:07:57.661291	\N	\N	ACTIVO
608	1	pedido venta	2	ALTA	Se inserta registro cabecera de Pedido Venta #2	2025-12-05 14:08:13.794127	\N	\N	ACTIVO
609	1	pedido venta	2	ALTA	Detalle agregado (pedido 2, producto 1, cantidad 10)	2025-12-05 14:08:13.794127	\N	\N	ACTIVO
610	1	presupuesto venta	1	ALTA	Se inserta registro cabecera de Presupuesto Venta #1	2025-12-05 14:16:44.940804	\N	\N	ACTIVO
611	1	presupuesto venta	1	ALTA	Detalle agregado (presupuesto 1, producto 1, cantidad 100)	2025-12-05 14:16:44.940804	\N	\N	ACTIVO
612	1	presupuesto venta	1	MODIFICACION	Actualiza cabecera presupuesto #1: Cliente, Validez, Observación	2025-12-05 14:18:34.769139	\N	\N	ACTIVO
613	1	presupuesto venta	1	MODIFICACION	Actualiza producto 1 en presupuesto #1: cantidad 50	2025-12-05 14:18:34.769139	\N	\N	ACTIVO
615	1	presupuesto venta	2	ALTA	Se inserta registro cabecera de Presupuesto Venta #2	2025-12-05 14:23:46.883736	\N	\N	ACTIVO
616	1	presupuesto venta	2	ALTA	Detalle agregado (presupuesto 2, producto 1, cantidad 100)	2025-12-05 14:23:46.883736	\N	\N	ACTIVO
617	1	Presupuesto compra	17	ALTA	Presupuesto Compra: se crea cabecera #17 (UI Presupuesto: #17) por pedido 2. Total: 100000.	2025-12-05 14:29:47.917967	\N	\N	ACTIVO
618	1	Presupuesto compra	17	ALTA	Detalle Presupuesto Compra: presupuesto #17, materia prima: 1, cantidad: 10, precio 10000.	2025-12-05 14:29:47.917967	\N	\N	ACTIVO
619	1	Presupuesto compra	2	MODIFICACION	Update estado de Pedido Compra: pedido 2 → APROBADO por presupuesto #17.	2025-12-05 14:29:47.917967	\N	\N	ACTIVO
620	1	Orden compra	3	ALTA	Crea Orden de Compra #3 (presupuesto 17, proveedor 9, condicion CREDITO)	2025-12-05 14:30:03.908632	\N	\N	ACTIVO
621	1	Orden compra	3	ALTA	Detalle Orden de Compra: orden #3, materia prima: 1, cantidad: 10, precio 10000.	2025-12-05 14:30:03.908632	\N	\N	ACTIVO
622	1	Orden compra	17	MODIFICACION	Actualiza presupuesto 17 a APROBADO  por generación de Orden #3	2025-12-05 14:30:03.908632	\N	\N	ACTIVO
623	1	Gestionar compra / Factura	22	ALTA	Factura #22 | OC:3 | Nro:001-002-0011113 | Timbrado:23424242 | Emisión:2025-12-05 | Total:100000 | Tipo:CREDITO | Cuotas:10 | %Int:10	2025-12-05 14:36:50.699257	\N	\N	ACTIVO
624	1	Gestionar compra / Factura	3	MODIFICACION	OC #3 → FACTURADA (Factura #22)	2025-12-05 14:36:50.699257	\N	\N	ACTIVO
625	1	Gestionar compra / Factura	22	ALTA	Detalle Factura #22 | Materia Prima:1 | Cant:10 | Precio:10000 | IVA:4761	2025-12-05 14:36:50.699257	\N	\N	ACTIVO
626	1	Gestionar compra / Factura	22	ALTA	IVA COMPRA Factura #22 | Exento:0 | IVA5:4761 | IVA10:0	2025-12-05 14:36:50.699257	\N	\N	ACTIVO
627	1	Gestionar compra / Factura	22	MODIFICACION	Stock actualizado: +10 unid. | Materia Prima:1 | Depósito:1 | Factura #22	2025-12-05 14:36:50.699257	\N	\N	ACTIVO
628	1	Gestionar compra / Factura	15	ALTA	CtaPagar #15 | Factura #22 | Total:110000 | Estado:PENDIENTE	2025-12-05 14:36:50.699257	\N	\N	ACTIVO
629	1	Gestionar compra / Factura	17	MODIFICACION	Presupuesto #17 → FINALIZADO (Factura #22)	2025-12-05 14:36:50.699257	\N	\N	ACTIVO
630	1	Gestionar compra / Factura	2	MODIFICACION	Pedido #2 → FINALIZADO (Factura #22)	2025-12-05 14:36:50.699257	\N	\N	ACTIVO
631	1	Gestionar compra / Factura	22	ALTA	IVA COMPRA Factura #22 | Exento:0 | IVA5:4761 | IVA10:0	2025-12-05 14:37:32.261447	\N	\N	ACTIVO
632	1	Gestionar compra / Factura	3	MODIFICACION	OC #3 → FINALIZADO (Factura #22)	2025-12-05 14:37:32.261447	\N	\N	ACTIVO
633	1	Gestionar compra / Factura	17	MODIFICACION	Presupuesto #17 → FINALIZADO (Factura #22)	2025-12-05 14:37:32.261447	\N	\N	ACTIVO
634	1	Gestionar compra / Factura	2	MODIFICACION	Pedido #2 → FINALIZADO (Factura #22)	2025-12-05 14:37:32.261447	\N	\N	ACTIVO
635	1	Gestionar compra / Factura	22	ALTA	UPDATE stock sin NR: +10 unid. | Materia Prima:1 | Depósito:1 | Factura #22	2025-12-05 14:37:32.261447	\N	\N	ACTIVO
636	1	Gestionar compra / Factura	22	MODIFICACION	Factura #22 aprobada | Estado: EMITIDA | CtaPagar: PENDIENTE | Tipo: CREDITO	2025-12-05 14:37:32.261447	\N	\N	ACTIVO
637	1	apertura cierre caja	7	ALTA	Se abre caja #1 con monto inicial 1000000	2025-12-05 14:44:39.06865	\N	\N	ACTIVO
638	1	Gestionar Venta / Factura	2	MODIFICACION	Pedido #2 → FACTURADO (Factura #23)	2025-12-05 14:45:19.105255	\N	\N	ACTIVO
639	1	Gestionar Venta / Factura	23	ALTA	Factura #23 | Cliente:1 | Total:275625 | Tipo:CONTADO	2025-12-05 14:45:19.105255	\N	\N	ACTIVO
640	1	Nota de Crédito Venta	8	ALTA	Alta Nota Venta: id=8 | tipo=CREDITO | motivo=11 | cliente=1 | total=275625 | factura=23	2025-12-05 14:46:39.184167	\N	\N	ACTIVO
641	1	Nota de Crédito Venta	8	MODIFICACION	Stock repuesto: +10 unid. | Producto:1 | Depósito:1 | Nota:8	2025-12-05 14:46:39.184167	\N	\N	ACTIVO
642	1	Nota de Crédito Venta	23	MODIFICACION	Factura #23 → ANULADA (Nota de Crédito #8)	2025-12-05 14:46:39.184167	\N	\N	ACTIVO
643	1	Nota de Crédito Venta	2	MODIFICACION	Pedido #2 → ANULADO (Factura #23 anulada)	2025-12-05 14:46:39.184167	\N	\N	ACTIVO
644	1	Gestionar Venta / Factura	3	MODIFICACION	Pedido #3 → FACTURADO (Factura #24)	2025-12-05 14:51:36.371717	\N	\N	ACTIVO
645	1	Gestionar Venta / Factura	24	ALTA	Factura #24 | Cliente:17 | Total:262500 | Tipo:CONTADO	2025-12-05 14:51:36.371717	\N	\N	ACTIVO
646	1	Cobro	7	ALTA	Alta Cobro: id=7 | recibo=REC-00000001 | cliente=17 | total=262500	2025-12-05 14:52:38.744567	\N	\N	ACTIVO
647	1	Cobro	24	MODIFICACION	Factura #24 → PAGADA (Total pagado: 262500)	2025-12-05 14:52:38.744567	\N	\N	ACTIVO
648	1	Cobro	3	MODIFICACION	Pedido #3 → FINALIZADO (Factura #24 pagada completamente)	2025-12-05 14:52:38.744567	\N	\N	ACTIVO
649	1	Cobro	8	ALTA	Arqueo creado: id=8 | Efectivo=100000 | Cheque=0	2025-12-05 14:52:38.744567	\N	\N	ACTIVO
651	1	apertura cierre caja	8	ALTA	Se abre caja #1 con monto inicial 1000000	2025-12-05 14:54:17.124423	\N	\N	ACTIVO
652	1	Nota de Crédito/Débito	16	ALTA	Nota de CREDITO #16 emitida | Factura #21 | Motivo: 1 | Total: 100000	2025-12-05 15:02:36.167378	\N	\N	ACTIVO
653	1	Nota de Crédito/Débito	16	ALTA	Detalle Nota #16: Materia Prima 1, cantidad 10, precio 10000, IVA 5%	2025-12-05 15:02:36.167378	\N	\N	ACTIVO
656	1	Nota de Crédito/Débito	2	MODIFICACION	OC #2 → EMITIDA (Anulación Factura #21 por Nota de Crédito #16)	2025-12-05 15:02:36.167378	\N	\N	ACTIVO
657	1	Nota de Remisión (Compra)	2	ALTA	Alta NR id=2 | OC=2 | proveedor=9 | depósito=1 | total=100000 | nroLegal=111-111-1111111	2025-12-05 15:33:18.26525	\N	\N	ACTIVO
658	1	Nota de Remisión (Compra)	2	MODIFICACION	Stock ingresado: +10 unid. | Materia Prima:1 | Depósito:1 | NR #2	2025-12-05 15:33:18.26525	\N	\N	ACTIVO
659	1	pedido venta	4	ALTA	Se inserta registro cabecera de Pedido Venta #4	2025-12-05 15:59:40.396935	\N	\N	ACTIVO
660	1	pedido venta	4	ALTA	Detalle agregado (pedido 4, producto 1, cantidad 10)	2025-12-05 15:59:40.396935	\N	\N	ACTIVO
661	1	Gestionar Venta / Factura	4	MODIFICACION	Pedido #4 → FACTURADO (Factura #28)	2025-12-05 16:21:12.118171	\N	\N	ACTIVO
662	1	Gestionar Venta / Factura	28	ALTA	Factura #28 | Cliente:1 | Total:275625 | Tipo:CONTADO	2025-12-05 16:21:12.118171	\N	\N	ACTIVO
663	1	pedido venta	5	ALTA	Se inserta registro cabecera de Pedido Venta #5	2025-12-05 17:15:03.054163	\N	\N	ACTIVO
664	1	pedido venta	5	ALTA	Detalle agregado (pedido 5, producto 1, cantidad 2)	2025-12-05 17:15:03.054163	\N	\N	ACTIVO
665	1	pedido compra	3	ALTA	Se inserta registro cabecera de Pedido Compra	2025-12-05 18:37:43.667028	\N	\N	ACTIVO
666	1	pedido compra	1	ALTA	Detalle agregado (pedido 3, materia prima 1, cant 10)	2025-12-05 18:37:43.667028	\N	\N	ACTIVO
667	1	Presupuesto compra	18	ALTA	Presupuesto Compra: se crea cabecera #18 (UI Presupuesto: #18) por pedido 3. Total: 99000.	2025-12-05 18:38:13.734765	\N	\N	ACTIVO
668	1	Presupuesto compra	18	ALTA	Detalle Presupuesto Compra: presupuesto #18, materia prima: 1, cantidad: 10, precio 10000.	2025-12-05 18:38:13.734765	\N	\N	ACTIVO
669	1	Presupuesto compra	3	MODIFICACION	Update estado de Pedido Compra: pedido 3 → APROBADO por presupuesto #18.	2025-12-05 18:38:13.734765	\N	\N	ACTIVO
670	1	Presupuesto compra	18	INACTIVACION	[2025-12-05 18:38:27] Se ANULA el Presupuesto de Compra #18. Pedido #3 vuelve a PENDIENTE.	2025-12-05 18:38:27.062021	\N	\N	ACTIVO
671	1	Presupuesto compra	19	ALTA	Presupuesto Compra: se crea cabecera #19 (UI Presupuesto: #19) por pedido 3. Total: 100000.	2025-12-05 18:39:17.217153	\N	\N	ACTIVO
672	1	Presupuesto compra	19	ALTA	Detalle Presupuesto Compra: presupuesto #19, materia prima: 1, cantidad: 10, precio 10000.	2025-12-05 18:39:17.217153	\N	\N	ACTIVO
673	1	Presupuesto compra	3	MODIFICACION	Update estado de Pedido Compra: pedido 3 → APROBADO por presupuesto #19.	2025-12-05 18:39:17.217153	\N	\N	ACTIVO
674	1	Orden compra	4	ALTA	Crea Orden de Compra #4 (presupuesto 19, proveedor 9, condicion CREDITO)	2025-12-05 18:39:40.972841	\N	\N	ACTIVO
675	1	Orden compra	4	ALTA	Detalle Orden de Compra: orden #4, materia prima: 1, cantidad: 10, precio 10000.	2025-12-05 18:39:40.972841	\N	\N	ACTIVO
676	1	Orden compra	19	MODIFICACION	Actualiza presupuesto 19 a APROBADO  por generación de Orden #4	2025-12-05 18:39:40.972841	\N	\N	ACTIVO
677	1	pedido compra	4	ALTA	Se inserta registro cabecera de Pedido Compra	2025-12-05 19:05:53.980892	\N	\N	ACTIVO
678	1	pedido compra	1	ALTA	Detalle agregado (pedido 4, materia prima 1, cant 10)	2025-12-05 19:05:53.980892	\N	\N	ACTIVO
679	1	Presupuesto compra	20	ALTA	Presupuesto Compra: se crea cabecera #20 (UI Presupuesto: #20) por pedido 4. Total: 100000.	2025-12-05 19:06:16.350221	\N	\N	ACTIVO
680	1	Presupuesto compra	20	ALTA	Detalle Presupuesto Compra: presupuesto #20, materia prima: 1, cantidad: 10, precio 10000.	2025-12-05 19:06:16.350221	\N	\N	ACTIVO
681	1	Presupuesto compra	4	MODIFICACION	Update estado de Pedido Compra: pedido 4 → APROBADO por presupuesto #20.	2025-12-05 19:06:16.350221	\N	\N	ACTIVO
682	1	Orden compra	5	ALTA	Crea Orden de Compra #5 (presupuesto 20, proveedor 9, condicion CONTADO)	2025-12-05 19:06:29.764437	\N	\N	ACTIVO
683	1	Orden compra	5	ALTA	Detalle Orden de Compra: orden #5, materia prima: 1, cantidad: 10, precio 10000.	2025-12-05 19:06:29.764437	\N	\N	ACTIVO
684	1	Orden compra	20	MODIFICACION	Actualiza presupuesto 20 a APROBADO  por generación de Orden #5	2025-12-05 19:06:29.764437	\N	\N	ACTIVO
685	1	Gestionar compra / Factura	23	ALTA	Factura #23 | OC:5 | Nro:001-002-0011533 | Timbrado:15151515 | Emisión:2025-12-05 | Total:100000 | Tipo:CONTADO | Cuotas:0 | %Int:0	2025-12-05 19:07:10.053321	\N	\N	ACTIVO
686	1	Gestionar compra / Factura	5	MODIFICACION	OC #5 → FACTURADA (Factura #23)	2025-12-05 19:07:10.053321	\N	\N	ACTIVO
687	1	Gestionar compra / Factura	23	ALTA	Detalle Factura #23 | Materia Prima:1 | Cant:10 | Precio:10000 | IVA:4761	2025-12-05 19:07:10.053321	\N	\N	ACTIVO
688	1	Gestionar compra / Factura	23	ALTA	IVA COMPRA Factura #23 | Exento:0 | IVA5:4761 | IVA10:0	2025-12-05 19:07:10.053321	\N	\N	ACTIVO
689	1	Gestionar compra / Factura	23	MODIFICACION	Stock actualizado: +10 unid. | Materia Prima:1 | Depósito:1 | Factura #23	2025-12-05 19:07:10.053321	\N	\N	ACTIVO
690	1	Gestionar compra / Factura	16	ALTA	CtaPagar #16 | Factura #23 | Total:100000 | Estado:PENDIENTE	2025-12-05 19:07:10.053321	\N	\N	ACTIVO
691	1	Gestionar compra / Factura	20	MODIFICACION	Presupuesto #20 → FINALIZADO (Factura #23)	2025-12-05 19:07:10.053321	\N	\N	ACTIVO
692	1	Gestionar compra / Factura	4	MODIFICACION	Pedido #4 → FINALIZADO (Factura #23)	2025-12-05 19:07:10.053321	\N	\N	ACTIVO
693	1	pedido compra	5	ALTA	Se inserta registro cabecera de Pedido Compra	2025-12-05 19:08:46.429585	\N	\N	ACTIVO
694	1	pedido compra	1	ALTA	Detalle agregado (pedido 5, materia prima 1, cant 10)	2025-12-05 19:08:46.429585	\N	\N	ACTIVO
695	1	Presupuesto compra	21	ALTA	Presupuesto Compra: se crea cabecera #21 (UI Presupuesto: #21) por pedido 5. Total: 100000.	2025-12-05 19:09:00.601839	\N	\N	ACTIVO
696	1	Presupuesto compra	21	ALTA	Detalle Presupuesto Compra: presupuesto #21, materia prima: 1, cantidad: 10, precio 10000.	2025-12-05 19:09:00.601839	\N	\N	ACTIVO
697	1	Presupuesto compra	5	MODIFICACION	Update estado de Pedido Compra: pedido 5 → APROBADO por presupuesto #21.	2025-12-05 19:09:00.601839	\N	\N	ACTIVO
698	1	Orden compra	6	ALTA	Crea Orden de Compra #6 (presupuesto 21, proveedor 9, condicion CONTADO)	2025-12-05 19:09:10.321067	\N	\N	ACTIVO
699	1	Orden compra	6	ALTA	Detalle Orden de Compra: orden #6, materia prima: 1, cantidad: 10, precio 10000.	2025-12-05 19:09:10.321067	\N	\N	ACTIVO
700	1	Orden compra	21	MODIFICACION	Actualiza presupuesto 21 a APROBADO  por generación de Orden #6	2025-12-05 19:09:10.321067	\N	\N	ACTIVO
701	1	Gestionar compra / Factura	24	ALTA	Factura #24 | OC:6 | Nro:001-002-0011178 | Timbrado:23424265 | Emisión:2025-12-05 | Total:100000 | Tipo:CONTADO | Cuotas:0 | %Int:0	2025-12-05 19:09:38.559592	\N	\N	ACTIVO
702	1	Gestionar compra / Factura	6	MODIFICACION	OC #6 → FACTURADA (Factura #24)	2025-12-05 19:09:38.559592	\N	\N	ACTIVO
703	1	Gestionar compra / Factura	24	ALTA	Detalle Factura #24 | Materia Prima:1 | Cant:10 | Precio:10000 | IVA:4761	2025-12-05 19:09:38.559592	\N	\N	ACTIVO
704	1	Gestionar compra / Factura	24	ALTA	IVA COMPRA Factura #24 | Exento:0 | IVA5:4761 | IVA10:0	2025-12-05 19:09:38.559592	\N	\N	ACTIVO
705	1	Gestionar compra / Factura	24	MODIFICACION	Stock actualizado: +10 unid. | Materia Prima:1 | Depósito:1 | Factura #24	2025-12-05 19:09:38.559592	\N	\N	ACTIVO
706	1	Gestionar compra / Factura	17	ALTA	CtaPagar #17 | Factura #24 | Total:100000 | Estado:PENDIENTE	2025-12-05 19:09:38.559592	\N	\N	ACTIVO
707	1	Gestionar compra / Factura	21	MODIFICACION	Presupuesto #21 → FINALIZADO (Factura #24)	2025-12-05 19:09:38.559592	\N	\N	ACTIVO
708	1	Gestionar compra / Factura	5	MODIFICACION	Pedido #5 → FINALIZADO (Factura #24)	2025-12-05 19:09:38.559592	\N	\N	ACTIVO
709	1	pedido venta	6	ALTA	Se inserta registro cabecera de Pedido Venta #6	2025-12-05 19:10:42.004322	\N	\N	ACTIVO
710	1	pedido venta	6	ALTA	Detalle agregado (pedido 6, producto 1, cantidad 10)	2025-12-05 19:10:42.004322	\N	\N	ACTIVO
711	1	presupuesto venta	3	ALTA	Se inserta registro cabecera de Presupuesto Venta #3	2025-12-05 19:13:13.872099	\N	\N	ACTIVO
712	1	presupuesto venta	3	ALTA	Detalle agregado (presupuesto 3, producto 1, cantidad 6)	2025-12-05 19:13:13.872099	\N	\N	ACTIVO
713	1	Gestionar Venta / Factura	6	MODIFICACION	Pedido #6 → FACTURADO (Factura #29)	2025-12-05 19:13:56.785902	\N	\N	ACTIVO
714	1	Gestionar Venta / Factura	29	ALTA	Factura #29 | Cliente:1 | Total:275625 | Tipo:CONTADO	2025-12-05 19:13:56.785902	\N	\N	ACTIVO
\.


--
-- TOC entry 5569 (class 0 OID 37769)
-- Dependencies: 251
-- Data for Name: cargos; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cargos (id_cargo, cargo_descripcion, estado_cargo, id_usuario) FROM stdin;
1	ADMIN	ACTIVO	1
2	JEFE DE COMPRAS	ACTIVO	1
3	JEFE DE VENTAS	ACTIVO	1
4	ENCARGADO DE COMPRAS	ACTIVO	1
5	ENCARGADO DE VENTAS	ACTIVO	1
\.


--
-- TOC entry 5550 (class 0 OID 37682)
-- Dependencies: 232
-- Data for Name: conductores; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.conductores (conductor_id, conductor_nombre, conductor_apellido, conductor_telefono, id_usuario) FROM stdin;
1	LUCAS	MEDINA	09999999	1
\.


--
-- TOC entry 5617 (class 0 OID 38059)
-- Dependencies: 299
-- Data for Name: control_calidad_detalle; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.control_calidad_detalle (calidad_id, producto_id, calidad_estado, calidad_cantidad, parametro_id, valor_medido, cumple_parametro) FROM stdin;
\.


--
-- TOC entry 5589 (class 0 OID 37855)
-- Dependencies: 271
-- Data for Name: control_calidad_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.control_calidad_produccion (calidad_id, calidad_fecha, calidad_estado, id_inspectores, id_usuario, terminado_id) FROM stdin;
\.


--
-- TOC entry 5591 (class 0 OID 37862)
-- Dependencies: 273
-- Data for Name: control_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.control_produccion (control_id, control_fecha, control_estado, id_inspectores, orden_id, id_usuario, producto_id, etapa_id, control_observacion) FROM stdin;
\.


--
-- TOC entry 5645 (class 0 OID 66159)
-- Dependencies: 327
-- Data for Name: control_produccion_consumo; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.control_produccion_consumo (control_id, id_materia_prima, cantidad_consumida) FROM stdin;
\.


--
-- TOC entry 5613 (class 0 OID 38018)
-- Dependencies: 295
-- Data for Name: control_produccion_detalle; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.control_produccion_detalle (control_id, producto_id, control_cantidad, control_descri) FROM stdin;
\.


--
-- TOC entry 5622 (class 0 OID 38078)
-- Dependencies: 304
-- Data for Name: costo_detalle_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.costo_detalle_produccion (costo_id, id_materia_prima, costo_cantidad, costo_precio, costo_detalle_id, costo_tipo, trabajadores_id, costo_concepto) FROM stdin;
\.


--
-- TOC entry 5577 (class 0 OID 37806)
-- Dependencies: 259
-- Data for Name: costo_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.costo_produccion (costo_id, costo_fecha, costo_estado, costo_total, id_usuario, orden_id) FROM stdin;
\.


--
-- TOC entry 5573 (class 0 OID 37783)
-- Dependencies: 255
-- Data for Name: cuentas_pagar; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cuentas_pagar (id_cuenta_pagar, monto_total, monto_pendiente, estado, fecha_emision, fecha_vencimiento, id_sucursal, id_usuario, id_proveedor, id_factura_compra, plazo_cuenta, nro_cuota) FROM stdin;
13	100000	100000	ANULADO	2025-12-05	2025-12-05	1	1	9	20	0	0
15	110000	110000	PENDIENTE	2025-12-05	2026-10-01	1	1	9	22	10	0
14	0	0	ANULADO	2025-12-05	2026-10-01	1	1	9	21	10	0
16	100000	100000	PENDIENTE	2025-12-05	2025-12-05	1	1	9	23	0	0
17	100000	100000	PENDIENTE	2025-12-05	2025-12-05	1	1	9	24	0	0
\.


--
-- TOC entry 5552 (class 0 OID 37689)
-- Dependencies: 234
-- Data for Name: deposito; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.deposito (deposito_id, deposito_descri, id_usuario) FROM stdin;
1	CENTRAL	1
\.


--
-- TOC entry 5557 (class 0 OID 37716)
-- Dependencies: 239
-- Data for Name: equipo_detalle; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.equipo_detalle (equipo_id, trabajadores_id, tarea_rol) FROM stdin;
\.


--
-- TOC entry 5540 (class 0 OID 37626)
-- Dependencies: 222
-- Data for Name: equipos_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.equipos_produccion (equipo_id, equipo_descri, id_usuario, equipo_estado, orden_id, equipo_fecha, id_sucursal) FROM stdin;
\.


--
-- TOC entry 5610 (class 0 OID 38003)
-- Dependencies: 292
-- Data for Name: etapa_detalle_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.etapa_detalle_produccion (etapa_id, producto_id, etapa_nombre, etapa_procedimiento, etapa_secuencia, etapa_tiempo_estimado, etapa_observaciones) FROM stdin;
\.


--
-- TOC entry 5538 (class 0 OID 37619)
-- Dependencies: 220
-- Data for Name: etapa_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.etapa_produccion (etapa_id, etapa_fecha, etapa_descri, id_usuario, producto_id, etapa_estado) FROM stdin;
\.


--
-- TOC entry 5597 (class 0 OID 37918)
-- Dependencies: 279
-- Data for Name: factura_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.factura_compra (id_factura_compra, numero_factura, timbrado, fac_fecha_vencimiento, fact_fecha_compra, fac_total, fac_estado, fac_plazo, fac_remision, id_usuario, id_sucursal, id_orden_compra, tipo_operacion) FROM stdin;
20	001-002-0011111	15698412	2025-12-05	2025-12-05	100000	ANULADO	CONTADO	0	1	1	1	CONTADO
22	001-002-0011113	23424242	2026-10-01	2025-12-05	100000	EMITIDA	10 CUOTAS	0	1	1	3	CREDITO
21	001-002-0011112	15698412	2026-10-01	2025-12-05	100000	ANULADO	10 CUOTAS	0	1	1	2	CREDITO
23	001-002-0011533	15151515	2025-12-05	2025-12-05	100000	PENDIENTE	CONTADO	0	1	1	5	CONTADO
24	001-002-0011178	23424265	2025-12-05	2025-12-05	100000	PENDIENTE	CONTADO	0	1	1	6	CONTADO
\.


--
-- TOC entry 5628 (class 0 OID 38112)
-- Dependencies: 310
-- Data for Name: factura_detalle_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.factura_detalle_compra (id_materia_prima, id_factura_compra, fac_cantidad, fac_iva, fac_precio) FROM stdin;
1	20	10	4761	10000
1	21	10	4761	10000
1	22	10	4761	10000
1	23	10	4761	10000
1	24	10	4761	10000
\.


--
-- TOC entry 5637 (class 0 OID 44083)
-- Dependencies: 319
-- Data for Name: historial_productos; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.historial_productos (id_historial, producto_id, campo_modificado, valor_anterior, valor_nuevo, fecha_modificacion, id_usuario, accion) FROM stdin;
\.


--
-- TOC entry 5554 (class 0 OID 37703)
-- Dependencies: 236
-- Data for Name: inspectores; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.inspectores (id_inspectores, id_personal, inspector_estado, id_usuario) FROM stdin;
\.


--
-- TOC entry 5599 (class 0 OID 37925)
-- Dependencies: 281
-- Data for Name: iva_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.iva_compra (iva_id, id_factura_compra, iva_fecha, iva_exento, iva_5, iva_10) FROM stdin;
11	20	2025-12-05	0	4761	0
13	21	2025-12-05	0	4761	0
15	22	2025-12-05	0	4761	0
16	23	2025-12-05	0	4761	0
17	24	2025-12-05	0	4761	0
\.


--
-- TOC entry 5621 (class 0 OID 38072)
-- Dependencies: 303
-- Data for Name: materia_prima; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.materia_prima (id_materia_prima, materia_prima_descripcion, materia_prima_estado, id_unidad, id_usuario, iva_id) FROM stdin;
1	LECHUGA	ACTIVO	1	1	1
2	TOMATE	ACTIVO	1	1	1
3	PAN DE HAMBURGUESA	ACTIVO	1	1	1
4	CEBOLLAS	INACTIVO	1	1	1
\.


--
-- TOC entry 5559 (class 0 OID 37722)
-- Dependencies: 241
-- Data for Name: modulos; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.modulos (modulo_id, modulo_descri, id_usuario) FROM stdin;
1	COMPRAS	1
2	VENTAS	1
3	PRODUCCION	1
4	ADMIN	1
\.


--
-- TOC entry 5603 (class 0 OID 37969)
-- Dependencies: 285
-- Data for Name: motivo; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.motivo (id_motivo, motivo_descripcion, id_usuario, categoria_motivo) FROM stdin;
3	Diferencia de Precio	1	NOTA_CREDITO
4	Descuento / Bonificación	1	NOTA_CREDITO
5	Error en Facturación	1	NOTA_CREDITO
6	Ajuste de Saldo	1	NOTA_CREDITO
7	Faltante	1	AJUSTE
8	Sobrante	1	AJUSTE
9	Merma	1	AJUSTE
10	Regularización	1	AJUSTE
1	Anulación Total	1	NOTA_CREDITO
2	Devolución de Mercadería	1	NOTA_CREDITO
11	Anulación Total Venta	1	NOTA_CREDITO_VENTA
12	Devolución de Mercadería Venta	1	NOTA_CREDITO_VENTA
\.


--
-- TOC entry 5605 (class 0 OID 37983)
-- Dependencies: 287
-- Data for Name: nota_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.nota_compra (id_nota_compra, nota_compra_tipo, nota_compra_fecha, nota_nro, nota_compra_timbrado, nota_compra_inicio, nota_compra_vencimiento, nota_compra_estado, nota_total, id_usuario, id_sucursal, id_proveedor, id_motivo, id_factura_compra) FROM stdin;
16	CREDITO	2025-12-05	20000102	12345678	2025-12-05	2025-12-12	EMITIDA	100000	1	1	9	1	21
\.


--
-- TOC entry 5627 (class 0 OID 38105)
-- Dependencies: 309
-- Data for Name: nota_detalle_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.nota_detalle_compra (id_materia_prima, id_nota_compra, nota_compra_cantidad, tipo_iva, nota_precio) FROM stdin;
1	16	10	5	10000
\.


--
-- TOC entry 5601 (class 0 OID 37934)
-- Dependencies: 283
-- Data for Name: nota_remision_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.nota_remision_compra (id_nota_remision, id_factura_compra, nota_fecha, nota_remision_total, nota_remision_nro, nota_estado, id_usuario, id_proveedor, deposito_id, conductor_id, vehiculo_id, id_orden_compra) FROM stdin;
2	\N	2025-12-05	100000	111-111-1111111	EMITIDA	1	9	1	1	1	2
\.


--
-- TOC entry 5626 (class 0 OID 38098)
-- Dependencies: 308
-- Data for Name: nota_remision_detalle_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.nota_remision_detalle_compra (id_nota_remision, id_materia_prima, nota_cantidad, nota_remi_iva) FROM stdin;
2	1	10	5
\.


--
-- TOC entry 5583 (class 0 OID 37827)
-- Dependencies: 265
-- Data for Name: orden_de_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.orden_de_compra (id_orden_compra, orden_fecha, orden_estado, orden_total, id_presupuesto_compra, id_proveedor, id_sucursal, id_usuario, orden_condicion) FROM stdin;
1	2025-12-05	ANULADO	100000	16	9	1	1	CONTADO
3	2025-12-05	FINALIZADO	100000	17	9	1	1	CREDITO
2	2025-12-05	EMITIDA	100000	16	9	1	1	CREDITO
4	2025-12-05	EMITIDA	100000	19	9	1	1	CREDITO
5	2025-12-05	FACTURADA	100000	20	9	1	1	CONTADO
6	2025-12-05	FACTURADA	100000	21	9	1	1	CONTADO
\.


--
-- TOC entry 5632 (class 0 OID 38131)
-- Dependencies: 314
-- Data for Name: orden_detalle_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.orden_detalle_compra (id_materia_prima, id_orden_compra, oc_cantidad_compra, oc_precio_compra) FROM stdin;
1	1	10	10000
1	2	10	10000
1	3	10	10000
1	4	10	10000
1	5	10	10000
1	6	10	10000
\.


--
-- TOC entry 5614 (class 0 OID 38023)
-- Dependencies: 296
-- Data for Name: orden_detalle_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.orden_detalle_produccion (orden_id, producto_id, orden_prod_cantidad, cantidad_pendiente) FROM stdin;
\.


--
-- TOC entry 5585 (class 0 OID 37841)
-- Dependencies: 267
-- Data for Name: orden_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.orden_produccion (orden_id, orden_prod_fecha, orden_prod_fecha_entrega, orden_prod_estado, id_usuario, id_pedido_produccion) FROM stdin;
\.


--
-- TOC entry 5616 (class 0 OID 38053)
-- Dependencies: 298
-- Data for Name: parametros_control; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.parametros_control (parametro_id, parametro_descri, producto_id, id_usuario, parametro_estado) FROM stdin;
\.


--
-- TOC entry 5625 (class 0 OID 38093)
-- Dependencies: 307
-- Data for Name: pedido_detalle_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.pedido_detalle_compra (id_pedido_compra, id_materia_prima, cantidad_pedido) FROM stdin;
1	1	10
2	1	10
3	1	10
4	1	10
5	1	10
\.


--
-- TOC entry 5643 (class 0 OID 66012)
-- Dependencies: 325
-- Data for Name: pedido_detalle_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.pedido_detalle_produccion (id_pedido_produccion, producto_id, cantidad_pedido) FROM stdin;
\.


--
-- TOC entry 5624 (class 0 OID 38088)
-- Dependencies: 306
-- Data for Name: pedido_materia_detalle_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.pedido_materia_detalle_produccion (id_pedido_mat_prod, id_materia_prima, ped_mat_prod_cantidad, cantidad_repuesta) FROM stdin;
\.


--
-- TOC entry 5546 (class 0 OID 37668)
-- Dependencies: 228
-- Data for Name: pedido_materia_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.pedido_materia_produccion (id_pedido_mat_prod, ped_mat_prod_fecha, ped_mat_prod_estado, id_usuario, id_sucursal, deposito_id) FROM stdin;
\.


--
-- TOC entry 5641 (class 0 OID 66001)
-- Dependencies: 323
-- Data for Name: pedido_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.pedido_produccion (id_pedido_produccion, pedido_prod_fecha_emision, pedido_prod_estado, id_tipo_pedido, id_usuario, id_sucursal, pedido_prod_observaciones, pedido_prod_ultima_modificacion) FROM stdin;
\.


--
-- TOC entry 5575 (class 0 OID 37799)
-- Dependencies: 257
-- Data for Name: pedidos_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.pedidos_compra (id_pedido_compra, pedido_fecha_emision, pedido_estado, id_usuario, id_sucursal, pedido_observaciones, pedido_ultima_modificacion) FROM stdin;
1	2025-12-05	FINALIZADO	1	1		2025-12-05 13:46:52.130157
2	2025-12-05	FINALIZADO	1	1		2025-12-05 14:37:32.261447
3	2025-12-05	APROBADO	1	1		2025-12-05 18:39:17.217153
4	2025-12-05	FINALIZADO	1	1		2025-12-05 19:07:10.053321
5	2025-12-05	FINALIZADO	1	1		2025-12-05 19:09:38.559592
\.


--
-- TOC entry 5593 (class 0 OID 37869)
-- Dependencies: 275
-- Data for Name: perdidas; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.perdidas (perdidas_id, perdida_estado, perdida_fecha, tipo_perdida_id, calidad_id, control_id, id_usuario) FROM stdin;
\.


--
-- TOC entry 5611 (class 0 OID 38008)
-- Dependencies: 293
-- Data for Name: perdidas_detalle; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.perdidas_detalle (perdidas_id, producto_id, perdida_cantidad, perdida_motivo) FROM stdin;
\.


--
-- TOC entry 5534 (class 0 OID 37605)
-- Dependencies: 216
-- Data for Name: personal; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.personal (id_personal, personal_estado, personal_nombre, personal_apellido, personal_telefono, personal_ci, id_cargo, id_usuario, id_sucursal) FROM stdin;
1	ACTIVO	LUCAS	MEDINA	0974203860	5574408	1	1	1
\.


--
-- TOC entry 5581 (class 0 OID 37820)
-- Dependencies: 263
-- Data for Name: presupuesto_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.presupuesto_compra (id_presupuesto_compra, presu_total, presu_fecha, presu_estado, id_pedido_compra, id_usuario, id_sucursal, id_proveedor, descuento_total, presu_observaciones, presu_ultima_modificacion) FROM stdin;
16	100000	2025-12-05	FINALIZADO	1	1	1	9	0.00		2025-12-05 13:46:52.130157
17	100000	2025-12-05	FINALIZADO	2	1	1	9	0.00		2025-12-05 14:37:32.261447
18	99000	2025-12-05	ANULADO	3	1	1	9	1000.00		2025-12-05 18:38:27.062021
19	100000	2025-12-05	APROBADO	3	1	1	9	0.00		2025-12-05 18:39:40.972841
20	100000	2025-12-05	FINALIZADO	4	1	1	9	0.00		2025-12-05 19:07:10.053321
21	100000	2025-12-05	FINALIZADO	5	1	1	9	0.00		2025-12-05 19:09:38.559592
\.


--
-- TOC entry 5633 (class 0 OID 38136)
-- Dependencies: 315
-- Data for Name: presupuesto_detalle_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.presupuesto_detalle_compra (id_presupuesto_compra, id_materia_prima, detalle_presu_cantidad, detalle_presu_precio_compra, descuento, detalle_presu_iva) FROM stdin;
16	1	10	10000	0.00	4760.00
17	1	10	10000	0.00	4760.00
18	1	10	10000	1000.00	4710.00
19	1	10	10000	0.00	4760.00
20	1	10	10000	0.00	4760.00
21	1	10	10000	0.00	4760.00
\.


--
-- TOC entry 5587 (class 0 OID 37848)
-- Dependencies: 269
-- Data for Name: producto_terminado; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.producto_terminado (terminado_id, orden_id, terminado_fecha, id_usuario) FROM stdin;
\.


--
-- TOC entry 5609 (class 0 OID 37997)
-- Dependencies: 291
-- Data for Name: productos; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.productos (producto_id, producto_precio, producto_descri, id_unidad, id_usuario, iva_id, producto_estado, id_tipo_producto) FROM stdin;
1	25000	HAMBURGUESA CON BACON	1	1	1	ACTIVO	1
5	10000	PAPAS FRITAS	1	1	1	INACTIVO	1
\.


--
-- TOC entry 5612 (class 0 OID 38013)
-- Dependencies: 294
-- Data for Name: productos_terminados_detalle; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.productos_terminados_detalle (terminado_id, producto_id, terminado_cantidad, deposito_id, terminado_fecha_elab, terminado_fecha_venc) FROM stdin;
\.


--
-- TOC entry 5561 (class 0 OID 37736)
-- Dependencies: 243
-- Data for Name: proveedor; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.proveedor (id_proveedor, id_usuario, razon_social, ruc_proveedor, telefono_proveedor, direccion_proveedor, email_proveedor, estado_proveedor) FROM stdin;
9	1	ANA ROLON	5574408-1	0974203860	Capiata	ana@gmail.com	ACTIVO
\.


--
-- TOC entry 5579 (class 0 OID 37813)
-- Dependencies: 261
-- Data for Name: reposicion_materia; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.reposicion_materia (reposicion_id, reposicion_fecha, reposicion_estado, deposito_id, id_usuario, id_pedido_mat_prod) FROM stdin;
\.


--
-- TOC entry 5623 (class 0 OID 38083)
-- Dependencies: 305
-- Data for Name: reposicion_materia_detalle; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.reposicion_materia_detalle (reposicion_id, id_materia_prima, reposicion_cantidad) FROM stdin;
\.


--
-- TOC entry 5630 (class 0 OID 38120)
-- Dependencies: 312
-- Data for Name: stock_materia_prima; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.stock_materia_prima (id_stock, id_materia_prima, stock_cantidad_minima, stock_cantidad_maxima, cantidad_existente, deposito_id, id_usuario) FROM stdin;
2	2	50	300	100	1	1
3	3	50	300	100	1	1
1	1	50	300	110	1	1
\.


--
-- TOC entry 5619 (class 0 OID 38065)
-- Dependencies: 301
-- Data for Name: stock_producto; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.stock_producto (id_stock_productos, producto_id, deposito_id, stock_prod_ven, stock_prod_existente, id_usuario) FROM stdin;
2	5	1	2026-01-05	5	1
1	1	1	2026-01-05	790	1
\.


--
-- TOC entry 5565 (class 0 OID 37750)
-- Dependencies: 247
-- Data for Name: sucursales; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sucursales (id_sucursal, descripcion_sucursal, estado_sucursal, id_usuario) FROM stdin;
1	CENTRAL	ACTIVO	\N
\.


--
-- TOC entry 5567 (class 0 OID 37757)
-- Dependencies: 249
-- Data for Name: timbrado; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.timbrado (id_timbrado, timbrado_numero, timbrado_fecha_inicio, timbrado_fecha_fin, timbrado_estado, id_usuario) FROM stdin;
1	15698412	2025-01-01	2026-01-01	ACTIVO	1
\.


--
-- TOC entry 5563 (class 0 OID 37743)
-- Dependencies: 245
-- Data for Name: tipo_documento; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tipo_documento (id_tipo_documento, descripcion_tipo_documento, id_usuario) FROM stdin;
\.


--
-- TOC entry 5542 (class 0 OID 37633)
-- Dependencies: 224
-- Data for Name: tipo_iva; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tipo_iva (iva_id, iva_descri, id_usuario) FROM stdin;
1	5%	1
\.


--
-- TOC entry 5595 (class 0 OID 37911)
-- Dependencies: 277
-- Data for Name: tipo_operacion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tipo_operacion (id_tipo_operacion, descri_tipo_operacion, id_usuario) FROM stdin;
1	CREDITO	1
2	CONTADO	1
\.


--
-- TOC entry 5639 (class 0 OID 65988)
-- Dependencies: 321
-- Data for Name: tipo_pedido; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tipo_pedido (id_tipo_pedido, tipo_pedido_descri, tipo_pedido_estado, id_usuario) FROM stdin;
1	Pedido estándar	ACTIVO	1
2	Pedido urgente	ACTIVO	1
3	Pedido programado	ACTIVO	1
\.


--
-- TOC entry 5544 (class 0 OID 37661)
-- Dependencies: 226
-- Data for Name: tipo_perdida; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tipo_perdida (tipo_perdida_id, tipo_perdida_descri, id_usuario) FROM stdin;
\.


--
-- TOC entry 5638 (class 0 OID 44133)
-- Dependencies: 320
-- Data for Name: tipo_producto; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tipo_producto (cod_tipo_prod, t_p_descrip) FROM stdin;
1	HAMBURGUESAS
\.


--
-- TOC entry 5556 (class 0 OID 37710)
-- Dependencies: 238
-- Data for Name: trabajadores; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.trabajadores (trabajadores_id, id_usuario, id_personal, trabajador_estado, trabajador_rol, trabajador_turno, trabajador_costo_hora, id_etapa) FROM stdin;
\.


--
-- TOC entry 5607 (class 0 OID 37990)
-- Dependencies: 289
-- Data for Name: unidad_medida; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.unidad_medida (id_unidad, unidad_descri, id_usuario) FROM stdin;
1	KG	1
\.


--
-- TOC entry 5536 (class 0 OID 37612)
-- Dependencies: 218
-- Data for Name: usuarios; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.usuarios (id_usuario, estado_usuario, id_sucursal, modulo_id, username, usua_password, id_cargo, id_personal) FROM stdin;
1	ACTIVO	1	1	lucas	dc53fc4f621c80bdc2fa0329a6123708	1	\N
8	ACTIVO	1	1	sebas	e22d88eb8554776cf514204fe0702f9b	4	1
\.


--
-- TOC entry 5548 (class 0 OID 37675)
-- Dependencies: 230
-- Data for Name: vehiculos; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.vehiculos (vehiculo_id, vehiculo_marca, vehiculo_ano, vehiculo_color, id_usuario) FROM stdin;
1	TOYOTA	2010	ROJO	1
\.


--
-- TOC entry 5734 (class 0 OID 0)
-- Dependencies: 252
-- Name: ajuste_stock_id_ajuste_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.ajuste_stock_id_ajuste_seq', 1, true);


--
-- TOC entry 5735 (class 0 OID 0)
-- Dependencies: 316
-- Name: bitacora_id_bitacora_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.bitacora_id_bitacora_seq', 714, true);


--
-- TOC entry 5736 (class 0 OID 0)
-- Dependencies: 250
-- Name: cargos_id_cargo_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cargos_id_cargo_seq', 1, true);


--
-- TOC entry 5737 (class 0 OID 0)
-- Dependencies: 231
-- Name: conductores_conductor_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.conductores_conductor_id_seq', 1, false);


--
-- TOC entry 5738 (class 0 OID 0)
-- Dependencies: 270
-- Name: control_calidad_produccion_calidad_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.control_calidad_produccion_calidad_id_seq', 1, false);


--
-- TOC entry 5739 (class 0 OID 0)
-- Dependencies: 272
-- Name: control_produccion_control_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.control_produccion_control_id_seq', 1, false);


--
-- TOC entry 5740 (class 0 OID 0)
-- Dependencies: 326
-- Name: costo_detalle_produccion_costo_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.costo_detalle_produccion_costo_detalle_id_seq', 1, false);


--
-- TOC entry 5741 (class 0 OID 0)
-- Dependencies: 258
-- Name: costo_produccion_costo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.costo_produccion_costo_id_seq', 1, false);


--
-- TOC entry 5742 (class 0 OID 0)
-- Dependencies: 254
-- Name: cuentas_pagar_id_cuenta_pagar_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cuentas_pagar_id_cuenta_pagar_seq', 17, true);


--
-- TOC entry 5743 (class 0 OID 0)
-- Dependencies: 233
-- Name: deposito_deposito_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.deposito_deposito_id_seq', 1, false);


--
-- TOC entry 5744 (class 0 OID 0)
-- Dependencies: 221
-- Name: equipos_produccion_equipo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.equipos_produccion_equipo_id_seq', 1, false);


--
-- TOC entry 5745 (class 0 OID 0)
-- Dependencies: 219
-- Name: etapa_produccion_etapa_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.etapa_produccion_etapa_id_seq', 1, false);


--
-- TOC entry 5746 (class 0 OID 0)
-- Dependencies: 278
-- Name: factura_compra_id_factura_compra_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.factura_compra_id_factura_compra_seq', 24, true);


--
-- TOC entry 5747 (class 0 OID 0)
-- Dependencies: 318
-- Name: historial_productos_id_historial_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.historial_productos_id_historial_seq', 5, true);


--
-- TOC entry 5748 (class 0 OID 0)
-- Dependencies: 235
-- Name: inspectores_id_inspectores_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.inspectores_id_inspectores_seq', 1, false);


--
-- TOC entry 5749 (class 0 OID 0)
-- Dependencies: 280
-- Name: iva_compra_id_libro_compra_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.iva_compra_id_libro_compra_seq', 17, true);


--
-- TOC entry 5750 (class 0 OID 0)
-- Dependencies: 302
-- Name: materia_prima_id_materia_prima_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.materia_prima_id_materia_prima_seq', 4, true);


--
-- TOC entry 5751 (class 0 OID 0)
-- Dependencies: 240
-- Name: modulos_modulo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.modulos_modulo_id_seq', 1, false);


--
-- TOC entry 5752 (class 0 OID 0)
-- Dependencies: 284
-- Name: motivo_id_motivo_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.motivo_id_motivo_seq', 12, true);


--
-- TOC entry 5753 (class 0 OID 0)
-- Dependencies: 286
-- Name: nota_compra_id_nota_compra_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.nota_compra_id_nota_compra_seq', 16, true);


--
-- TOC entry 5754 (class 0 OID 0)
-- Dependencies: 282
-- Name: nota_remision_compra_id_nota_remision_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.nota_remision_compra_id_nota_remision_seq', 2, true);


--
-- TOC entry 5755 (class 0 OID 0)
-- Dependencies: 264
-- Name: orden_de_compra_id_orden_compra_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.orden_de_compra_id_orden_compra_seq', 1, false);


--
-- TOC entry 5756 (class 0 OID 0)
-- Dependencies: 266
-- Name: orden_produccion_orden_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.orden_produccion_orden_id_seq', 1, false);


--
-- TOC entry 5757 (class 0 OID 0)
-- Dependencies: 297
-- Name: parametros_control_parametro_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.parametros_control_parametro_id_seq', 1, false);


--
-- TOC entry 5758 (class 0 OID 0)
-- Dependencies: 227
-- Name: pedido_materia_produccion_id_pedido_mat_prod_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.pedido_materia_produccion_id_pedido_mat_prod_seq', 1, false);


--
-- TOC entry 5759 (class 0 OID 0)
-- Dependencies: 324
-- Name: pedido_produccion_id_pedido_produccion_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.pedido_produccion_id_pedido_produccion_seq', 1, false);


--
-- TOC entry 5760 (class 0 OID 0)
-- Dependencies: 256
-- Name: pedidos_compra_id_pedido_compra_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.pedidos_compra_id_pedido_compra_seq', 1, false);


--
-- TOC entry 5761 (class 0 OID 0)
-- Dependencies: 274
-- Name: perdidas_perdidas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.perdidas_perdidas_id_seq', 1, false);


--
-- TOC entry 5762 (class 0 OID 0)
-- Dependencies: 215
-- Name: personal_id_personal_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.personal_id_personal_seq', 1, false);


--
-- TOC entry 5763 (class 0 OID 0)
-- Dependencies: 262
-- Name: presupuesto_compra_id_presupuesto_compra_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.presupuesto_compra_id_presupuesto_compra_seq', 21, true);


--
-- TOC entry 5764 (class 0 OID 0)
-- Dependencies: 268
-- Name: producto_terminado_terminado_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.producto_terminado_terminado_id_seq', 1, false);


--
-- TOC entry 5765 (class 0 OID 0)
-- Dependencies: 290
-- Name: productos_producto_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.productos_producto_id_seq', 5, true);


--
-- TOC entry 5766 (class 0 OID 0)
-- Dependencies: 242
-- Name: proveedor_id_proveedor_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.proveedor_id_proveedor_seq', 9, true);


--
-- TOC entry 5767 (class 0 OID 0)
-- Dependencies: 260
-- Name: reposicion_materia_reposicion_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.reposicion_materia_reposicion_id_seq', 1, false);


--
-- TOC entry 5768 (class 0 OID 0)
-- Dependencies: 311
-- Name: stock_materia_prima_id_stock_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.stock_materia_prima_id_stock_seq', 2, true);


--
-- TOC entry 5769 (class 0 OID 0)
-- Dependencies: 300
-- Name: stock_producto_id_stock_productos_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.stock_producto_id_stock_productos_seq', 5, true);


--
-- TOC entry 5770 (class 0 OID 0)
-- Dependencies: 246
-- Name: sucursales_id_sucursal_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.sucursales_id_sucursal_seq', 1, false);


--
-- TOC entry 5771 (class 0 OID 0)
-- Dependencies: 248
-- Name: timbrado_id_timbrado_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.timbrado_id_timbrado_seq', 1, true);


--
-- TOC entry 5772 (class 0 OID 0)
-- Dependencies: 244
-- Name: tipo_documento_id_tipo_documento_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tipo_documento_id_tipo_documento_seq', 1, false);


--
-- TOC entry 5773 (class 0 OID 0)
-- Dependencies: 223
-- Name: tipo_iva_iva_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tipo_iva_iva_id_seq', 1, false);


--
-- TOC entry 5774 (class 0 OID 0)
-- Dependencies: 276
-- Name: tipo_operacion_id_tipo_operacion_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tipo_operacion_id_tipo_operacion_seq', 1, false);


--
-- TOC entry 5775 (class 0 OID 0)
-- Dependencies: 322
-- Name: tipo_pedido_id_tipo_pedido_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tipo_pedido_id_tipo_pedido_seq', 3, true);


--
-- TOC entry 5776 (class 0 OID 0)
-- Dependencies: 225
-- Name: tipo_perdida_tipo_perdida_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tipo_perdida_tipo_perdida_id_seq', 1, false);


--
-- TOC entry 5777 (class 0 OID 0)
-- Dependencies: 237
-- Name: trabajadores_trabajadores_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.trabajadores_trabajadores_id_seq', 1, false);


--
-- TOC entry 5778 (class 0 OID 0)
-- Dependencies: 288
-- Name: unidad_medida_id_unidad_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.unidad_medida_id_unidad_seq', 1, false);


--
-- TOC entry 5779 (class 0 OID 0)
-- Dependencies: 217
-- Name: usuarios_id_usuario_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.usuarios_id_usuario_seq', 8, true);


--
-- TOC entry 5780 (class 0 OID 0)
-- Dependencies: 229
-- Name: vehiculos_vehiculo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.vehiculos_vehiculo_id_seq', 1, false);


--
-- TOC entry 5213 (class 2606 OID 38130)
-- Name: ajustes_detalle ajustes_detalle_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes_detalle
    ADD CONSTRAINT ajustes_detalle_pk PRIMARY KEY (id_materia_prima, id_ajuste, id_stock);


--
-- TOC entry 5131 (class 2606 OID 37781)
-- Name: ajustes ajustes_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes
    ADD CONSTRAINT ajustes_pk PRIMARY KEY (id_ajuste);


--
-- TOC entry 5219 (class 2606 OID 39051)
-- Name: bitacora bitacora_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bitacora
    ADD CONSTRAINT bitacora_pkey PRIMARY KEY (id_bitacora);


--
-- TOC entry 5129 (class 2606 OID 37774)
-- Name: cargos cargos_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cargos
    ADD CONSTRAINT cargos_pk PRIMARY KEY (id_cargo);


--
-- TOC entry 5109 (class 2606 OID 37687)
-- Name: conductores conductores_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.conductores
    ADD CONSTRAINT conductores_pk PRIMARY KEY (conductor_id);


--
-- TOC entry 5189 (class 2606 OID 38063)
-- Name: control_calidad_detalle control_calidad_detalle_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_detalle
    ADD CONSTRAINT control_calidad_detalle_pk PRIMARY KEY (calidad_id, producto_id);


--
-- TOC entry 5151 (class 2606 OID 37860)
-- Name: control_calidad_produccion control_calidad_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_produccion
    ADD CONSTRAINT control_calidad_produccion_pk PRIMARY KEY (calidad_id);


--
-- TOC entry 5235 (class 2606 OID 66164)
-- Name: control_produccion_consumo control_produccion_consumo_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion_consumo
    ADD CONSTRAINT control_produccion_consumo_pk PRIMARY KEY (control_id, id_materia_prima);


--
-- TOC entry 5182 (class 2606 OID 38022)
-- Name: control_produccion_detalle control_produccion_detalle_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion_detalle
    ADD CONSTRAINT control_produccion_detalle_pk PRIMARY KEY (control_id, producto_id);


--
-- TOC entry 5153 (class 2606 OID 37867)
-- Name: control_produccion control_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion
    ADD CONSTRAINT control_produccion_pk PRIMARY KEY (control_id);


--
-- TOC entry 5196 (class 2606 OID 66116)
-- Name: costo_detalle_produccion costo_detalle_produccion_id_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_detalle_produccion
    ADD CONSTRAINT costo_detalle_produccion_id_pk PRIMARY KEY (costo_detalle_id);


--
-- TOC entry 5138 (class 2606 OID 37811)
-- Name: costo_produccion costo_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_produccion
    ADD CONSTRAINT costo_produccion_pk PRIMARY KEY (costo_id);


--
-- TOC entry 5133 (class 2606 OID 37788)
-- Name: cuentas_pagar cuentas_pagar_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT cuentas_pagar_pk PRIMARY KEY (id_cuenta_pagar);


--
-- TOC entry 5111 (class 2606 OID 37694)
-- Name: deposito deposito_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposito
    ADD CONSTRAINT deposito_pk PRIMARY KEY (deposito_id);


--
-- TOC entry 5117 (class 2606 OID 37720)
-- Name: equipo_detalle equipo_detalle_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipo_detalle
    ADD CONSTRAINT equipo_detalle_pk PRIMARY KEY (equipo_id, trabajadores_id);


--
-- TOC entry 5099 (class 2606 OID 37631)
-- Name: equipos_produccion equipos_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipos_produccion
    ADD CONSTRAINT equipos_produccion_pk PRIMARY KEY (equipo_id);


--
-- TOC entry 5176 (class 2606 OID 38007)
-- Name: etapa_detalle_produccion etapa_detalle_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.etapa_detalle_produccion
    ADD CONSTRAINT etapa_detalle_produccion_pk PRIMARY KEY (etapa_id, producto_id);


--
-- TOC entry 5097 (class 2606 OID 37624)
-- Name: etapa_produccion etapa_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.etapa_produccion
    ADD CONSTRAINT etapa_produccion_pk PRIMARY KEY (etapa_id);


--
-- TOC entry 5159 (class 2606 OID 37923)
-- Name: factura_compra factura_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_compra
    ADD CONSTRAINT factura_compra_pk PRIMARY KEY (id_factura_compra);


--
-- TOC entry 5209 (class 2606 OID 38118)
-- Name: factura_detalle_compra factura_detalle_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_detalle_compra
    ADD CONSTRAINT factura_detalle_compra_pk PRIMARY KEY (id_materia_prima, id_factura_compra);


--
-- TOC entry 5221 (class 2606 OID 44091)
-- Name: historial_productos historial_productos_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historial_productos
    ADD CONSTRAINT historial_productos_pk PRIMARY KEY (id_historial);


--
-- TOC entry 5113 (class 2606 OID 37708)
-- Name: inspectores inspectores_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inspectores
    ADD CONSTRAINT inspectores_pk PRIMARY KEY (id_inspectores);


--
-- TOC entry 5161 (class 2606 OID 37932)
-- Name: iva_compra iva_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.iva_compra
    ADD CONSTRAINT iva_compra_pk PRIMARY KEY (iva_id);


--
-- TOC entry 5194 (class 2606 OID 38077)
-- Name: materia_prima materia_prima_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.materia_prima
    ADD CONSTRAINT materia_prima_pk PRIMARY KEY (id_materia_prima);


--
-- TOC entry 5119 (class 2606 OID 37727)
-- Name: modulos modulos_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT modulos_pk PRIMARY KEY (modulo_id);


--
-- TOC entry 5167 (class 2606 OID 37974)
-- Name: motivo motivo_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.motivo
    ADD CONSTRAINT motivo_pk PRIMARY KEY (id_motivo);


--
-- TOC entry 5169 (class 2606 OID 37988)
-- Name: nota_compra nota_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_compra
    ADD CONSTRAINT nota_compra_pk PRIMARY KEY (id_nota_compra);


--
-- TOC entry 5207 (class 2606 OID 38111)
-- Name: nota_detalle_compra nota_detalle_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_detalle_compra
    ADD CONSTRAINT nota_detalle_compra_pk PRIMARY KEY (id_materia_prima, id_nota_compra);


--
-- TOC entry 5164 (class 2606 OID 37939)
-- Name: nota_remision_compra nota_remision_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT nota_remision_compra_pk PRIMARY KEY (id_nota_remision);


--
-- TOC entry 5205 (class 2606 OID 38104)
-- Name: nota_remision_detalle_compra nota_remision_detalle_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_detalle_compra
    ADD CONSTRAINT nota_remision_detalle_compra_pk PRIMARY KEY (id_nota_remision, id_materia_prima);


--
-- TOC entry 5144 (class 2606 OID 37832)
-- Name: orden_de_compra orden_de_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_de_compra
    ADD CONSTRAINT orden_de_compra_pk PRIMARY KEY (id_orden_compra);


--
-- TOC entry 5215 (class 2606 OID 38135)
-- Name: orden_detalle_compra orden_detalle_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_detalle_compra
    ADD CONSTRAINT orden_detalle_compra_pk PRIMARY KEY (id_materia_prima, id_orden_compra);


--
-- TOC entry 5184 (class 2606 OID 38027)
-- Name: orden_detalle_produccion orden_detalle_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_detalle_produccion
    ADD CONSTRAINT orden_detalle_produccion_pk PRIMARY KEY (orden_id, producto_id);


--
-- TOC entry 5147 (class 2606 OID 37846)
-- Name: orden_produccion orden_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_produccion
    ADD CONSTRAINT orden_produccion_pk PRIMARY KEY (orden_id);


--
-- TOC entry 5186 (class 2606 OID 38058)
-- Name: parametros_control parametros_control_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.parametros_control
    ADD CONSTRAINT parametros_control_pk PRIMARY KEY (parametro_id);


--
-- TOC entry 5203 (class 2606 OID 38097)
-- Name: pedido_detalle_compra pedido_detalle_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_detalle_compra
    ADD CONSTRAINT pedido_detalle_compra_pk PRIMARY KEY (id_pedido_compra, id_materia_prima);


--
-- TOC entry 5233 (class 2606 OID 66017)
-- Name: pedido_detalle_produccion pedido_detalle_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_detalle_produccion
    ADD CONSTRAINT pedido_detalle_produccion_pk PRIMARY KEY (id_pedido_produccion, producto_id);


--
-- TOC entry 5201 (class 2606 OID 38092)
-- Name: pedido_materia_detalle_produccion pedido_materia_detalle_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_detalle_produccion
    ADD CONSTRAINT pedido_materia_detalle_produccion_pk PRIMARY KEY (id_pedido_mat_prod, id_materia_prima);


--
-- TOC entry 5105 (class 2606 OID 37673)
-- Name: pedido_materia_produccion pedido_materia_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_produccion
    ADD CONSTRAINT pedido_materia_produccion_pk PRIMARY KEY (id_pedido_mat_prod);


--
-- TOC entry 5231 (class 2606 OID 66009)
-- Name: pedido_produccion pedido_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_produccion
    ADD CONSTRAINT pedido_produccion_pk PRIMARY KEY (id_pedido_produccion);


--
-- TOC entry 5136 (class 2606 OID 37804)
-- Name: pedidos_compra pedidos_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedidos_compra
    ADD CONSTRAINT pedidos_compra_pk PRIMARY KEY (id_pedido_compra);


--
-- TOC entry 5178 (class 2606 OID 38012)
-- Name: perdidas_detalle perdidas_detalle_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas_detalle
    ADD CONSTRAINT perdidas_detalle_pk PRIMARY KEY (perdidas_id, producto_id);


--
-- TOC entry 5155 (class 2606 OID 37874)
-- Name: perdidas perdidas_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas
    ADD CONSTRAINT perdidas_pk PRIMARY KEY (perdidas_id);


--
-- TOC entry 5092 (class 2606 OID 37610)
-- Name: personal personal_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal
    ADD CONSTRAINT personal_pk PRIMARY KEY (id_personal);


--
-- TOC entry 5142 (class 2606 OID 37825)
-- Name: presupuesto_compra presupuesto_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_compra
    ADD CONSTRAINT presupuesto_compra_pk PRIMARY KEY (id_presupuesto_compra);


--
-- TOC entry 5217 (class 2606 OID 38140)
-- Name: presupuesto_detalle_compra presupuesto_detalle_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_detalle_compra
    ADD CONSTRAINT presupuesto_detalle_compra_pk PRIMARY KEY (id_presupuesto_compra, id_materia_prima);


--
-- TOC entry 5149 (class 2606 OID 37853)
-- Name: producto_terminado producto_terminado_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.producto_terminado
    ADD CONSTRAINT producto_terminado_pk PRIMARY KEY (terminado_id);


--
-- TOC entry 5174 (class 2606 OID 38002)
-- Name: productos productos_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_pk PRIMARY KEY (producto_id);


--
-- TOC entry 5180 (class 2606 OID 38017)
-- Name: productos_terminados_detalle productos_terminados_detalle_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos_terminados_detalle
    ADD CONSTRAINT productos_terminados_detalle_pk PRIMARY KEY (terminado_id, producto_id);


--
-- TOC entry 5121 (class 2606 OID 37741)
-- Name: proveedor proveedor_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.proveedor
    ADD CONSTRAINT proveedor_pk PRIMARY KEY (id_proveedor);


--
-- TOC entry 5199 (class 2606 OID 38087)
-- Name: reposicion_materia_detalle reposicion_materia_detalle_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia_detalle
    ADD CONSTRAINT reposicion_materia_detalle_pk PRIMARY KEY (reposicion_id, id_materia_prima);


--
-- TOC entry 5140 (class 2606 OID 37818)
-- Name: reposicion_materia reposicion_materia_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia
    ADD CONSTRAINT reposicion_materia_pk PRIMARY KEY (reposicion_id);


--
-- TOC entry 5211 (class 2606 OID 38125)
-- Name: stock_materia_prima stock_materia_prima_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_materia_prima
    ADD CONSTRAINT stock_materia_prima_pk PRIMARY KEY (id_stock);


--
-- TOC entry 5192 (class 2606 OID 38070)
-- Name: stock_producto stock_producto_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_producto
    ADD CONSTRAINT stock_producto_pk PRIMARY KEY (id_stock_productos);


--
-- TOC entry 5125 (class 2606 OID 37755)
-- Name: sucursales sucursales_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sucursales
    ADD CONSTRAINT sucursales_pk PRIMARY KEY (id_sucursal);


--
-- TOC entry 5127 (class 2606 OID 37762)
-- Name: timbrado timbrado_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.timbrado
    ADD CONSTRAINT timbrado_pk PRIMARY KEY (id_timbrado);


--
-- TOC entry 5123 (class 2606 OID 37748)
-- Name: tipo_documento tipo_documento_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_documento
    ADD CONSTRAINT tipo_documento_pk PRIMARY KEY (id_tipo_documento);


--
-- TOC entry 5101 (class 2606 OID 37638)
-- Name: tipo_iva tipo_iva_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_iva
    ADD CONSTRAINT tipo_iva_pk PRIMARY KEY (iva_id);


--
-- TOC entry 5157 (class 2606 OID 37916)
-- Name: tipo_operacion tipo_operacion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_operacion
    ADD CONSTRAINT tipo_operacion_pk PRIMARY KEY (id_tipo_operacion);


--
-- TOC entry 5228 (class 2606 OID 65993)
-- Name: tipo_pedido tipo_pedido_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_pedido
    ADD CONSTRAINT tipo_pedido_pk PRIMARY KEY (id_tipo_pedido);


--
-- TOC entry 5103 (class 2606 OID 37666)
-- Name: tipo_perdida tipo_perdida_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_perdida
    ADD CONSTRAINT tipo_perdida_pk PRIMARY KEY (tipo_perdida_id);


--
-- TOC entry 5226 (class 2606 OID 44137)
-- Name: tipo_producto tipo_producto_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_producto
    ADD CONSTRAINT tipo_producto_pk PRIMARY KEY (cod_tipo_prod);


--
-- TOC entry 5115 (class 2606 OID 37715)
-- Name: trabajadores trabajadores_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trabajadores
    ADD CONSTRAINT trabajadores_pk PRIMARY KEY (trabajadores_id);


--
-- TOC entry 5171 (class 2606 OID 37995)
-- Name: unidad_medida unidad_medida_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.unidad_medida
    ADD CONSTRAINT unidad_medida_pk PRIMARY KEY (id_unidad);


--
-- TOC entry 5095 (class 2606 OID 37617)
-- Name: usuarios usuarios_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pk PRIMARY KEY (id_usuario);


--
-- TOC entry 5107 (class 2606 OID 37680)
-- Name: vehiculos vehiculos_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.vehiculos
    ADD CONSTRAINT vehiculos_pk PRIMARY KEY (vehiculo_id);


--
-- TOC entry 5134 (class 1259 OID 44162)
-- Name: idx_cuentas_pagar_id_factura_compra; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cuentas_pagar_id_factura_compra ON public.cuentas_pagar USING btree (id_factura_compra);


--
-- TOC entry 5222 (class 1259 OID 44105)
-- Name: idx_historial_productos_fecha; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_historial_productos_fecha ON public.historial_productos USING btree (fecha_modificacion);


--
-- TOC entry 5223 (class 1259 OID 44104)
-- Name: idx_historial_productos_producto; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_historial_productos_producto ON public.historial_productos USING btree (producto_id);


--
-- TOC entry 5165 (class 1259 OID 44199)
-- Name: idx_motivo_categoria; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_motivo_categoria ON public.motivo USING btree (categoria_motivo);


--
-- TOC entry 5162 (class 1259 OID 44186)
-- Name: idx_nota_remision_compra_orden_compra; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_nota_remision_compra_orden_compra ON public.nota_remision_compra USING btree (id_orden_compra);


--
-- TOC entry 5145 (class 1259 OID 66051)
-- Name: idx_orden_produccion_pedido; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_orden_produccion_pedido ON public.orden_produccion USING btree (id_pedido_produccion);


--
-- TOC entry 5229 (class 1259 OID 66050)
-- Name: idx_pedido_produccion_estado_fecha; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_pedido_produccion_estado_fecha ON public.pedido_produccion USING btree (pedido_prod_estado, pedido_prod_fecha_emision DESC);


--
-- TOC entry 5090 (class 1259 OID 44205)
-- Name: idx_personal_sucursal; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_personal_sucursal ON public.personal USING btree (id_sucursal);


--
-- TOC entry 5172 (class 1259 OID 44102)
-- Name: idx_productos_tipo_producto; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_productos_tipo_producto ON public.productos USING btree (id_tipo_producto);


--
-- TOC entry 5190 (class 1259 OID 43502)
-- Name: idx_stock_producto_producto_deposito; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_stock_producto_producto_deposito ON public.stock_producto USING btree (producto_id, deposito_id);


--
-- TOC entry 5224 (class 1259 OID 44138)
-- Name: idx_tipo_producto_descrip; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tipo_producto_descrip ON public.tipo_producto USING btree (t_p_descrip);


--
-- TOC entry 5093 (class 1259 OID 43512)
-- Name: idx_usuarios_username; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_usuarios_username ON public.usuarios USING btree (username);


--
-- TOC entry 5197 (class 1259 OID 66117)
-- Name: uq_costo_detalle_mp; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX uq_costo_detalle_mp ON public.costo_detalle_produccion USING btree (costo_id, id_materia_prima) WHERE (((costo_tipo)::text = 'MP'::text) AND (id_materia_prima IS NOT NULL));


--
-- TOC entry 5187 (class 1259 OID 66135)
-- Name: uq_parametros_producto_descri_activo; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX uq_parametros_producto_descri_activo ON public.parametros_control USING btree (producto_id, lower(TRIM(BOTH FROM parametro_descri))) WHERE ((parametro_estado)::text = 'ACTIVO'::text);


--
-- TOC entry 5389 (class 2620 OID 66044)
-- Name: pedido_produccion trg_pedido_produccion_ultima_modificacion; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_pedido_produccion_ultima_modificacion BEFORE UPDATE ON public.pedido_produccion FOR EACH ROW EXECUTE FUNCTION public.fn_pedido_produccion_ultima_modificacion();


--
-- TOC entry 5387 (class 2620 OID 44143)
-- Name: pedidos_compra trg_pedidos_compra_ultima_modificacion; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_pedidos_compra_ultima_modificacion BEFORE UPDATE ON public.pedidos_compra FOR EACH ROW EXECUTE FUNCTION public.fn_pedidos_compra_ultima_modificacion();


--
-- TOC entry 5388 (class 2620 OID 44154)
-- Name: presupuesto_compra trg_presupuesto_compra_ultima_modificacion; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_presupuesto_compra_ultima_modificacion BEFORE UPDATE ON public.presupuesto_compra FOR EACH ROW EXECUTE FUNCTION public.fn_presupuesto_compra_ultima_modificacion();


--
-- TOC entry 5368 (class 2606 OID 38666)
-- Name: ajustes_detalle ajuste_stock_detalle_ajuste_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes_detalle
    ADD CONSTRAINT ajuste_stock_detalle_ajuste_fk FOREIGN KEY (id_ajuste) REFERENCES public.ajustes(id_ajuste);


--
-- TOC entry 5369 (class 2606 OID 39001)
-- Name: ajustes_detalle articulos_detalle_ajuste_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes_detalle
    ADD CONSTRAINT articulos_detalle_ajuste_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5361 (class 2606 OID 38996)
-- Name: nota_detalle_compra articulos_detalle_credito_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_detalle_compra
    ADD CONSTRAINT articulos_detalle_credito_compra_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5359 (class 2606 OID 39006)
-- Name: nota_remision_detalle_compra articulos_detalle_nota_remision_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_detalle_compra
    ADD CONSTRAINT articulos_detalle_nota_remision_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5372 (class 2606 OID 38981)
-- Name: orden_detalle_compra articulos_detalle_orden_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_detalle_compra
    ADD CONSTRAINT articulos_detalle_orden_compra_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5374 (class 2606 OID 38976)
-- Name: presupuesto_detalle_compra articulos_detalle_presu_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_detalle_compra
    ADD CONSTRAINT articulos_detalle_presu_compra_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5363 (class 2606 OID 38991)
-- Name: factura_detalle_compra articulos_detalle_registro_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_detalle_compra
    ADD CONSTRAINT articulos_detalle_registro_compra_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5365 (class 2606 OID 38986)
-- Name: stock_materia_prima articulos_stock_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_materia_prima
    ADD CONSTRAINT articulos_stock_detalle_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5236 (class 2606 OID 38661)
-- Name: personal cargos_empleados_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal
    ADD CONSTRAINT cargos_empleados_fk FOREIGN KEY (id_cargo) REFERENCES public.cargos(id_cargo);


--
-- TOC entry 5347 (class 2606 OID 38901)
-- Name: materia_prima categoria_articulos_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.materia_prima
    ADD CONSTRAINT categoria_articulos_fk FOREIGN KEY (id_unidad) REFERENCES public.unidad_medida(id_unidad);


--
-- TOC entry 5311 (class 2606 OID 38476)
-- Name: nota_remision_compra conductores_nota_remision_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT conductores_nota_remision_compra_fk FOREIGN KEY (conductor_id) REFERENCES public.conductores(conductor_id);


--
-- TOC entry 5341 (class 2606 OID 38766)
-- Name: control_calidad_detalle control_calidad_produccion_control_calidad_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_detalle
    ADD CONSTRAINT control_calidad_produccion_control_calidad_detalle_fk FOREIGN KEY (calidad_id) REFERENCES public.control_calidad_produccion(calidad_id);


--
-- TOC entry 5302 (class 2606 OID 38771)
-- Name: perdidas control_calidad_produccion_perdidas_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas
    ADD CONSTRAINT control_calidad_produccion_perdidas_fk FOREIGN KEY (calidad_id) REFERENCES public.control_calidad_produccion(calidad_id);


--
-- TOC entry 5385 (class 2606 OID 66165)
-- Name: control_produccion_consumo control_produccion_consumo_control_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion_consumo
    ADD CONSTRAINT control_produccion_consumo_control_fk FOREIGN KEY (control_id) REFERENCES public.control_produccion(control_id) ON DELETE CASCADE;


--
-- TOC entry 5335 (class 2606 OID 38776)
-- Name: control_produccion_detalle control_produccion_control_produccion_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion_detalle
    ADD CONSTRAINT control_produccion_control_produccion_detalle_fk FOREIGN KEY (control_id) REFERENCES public.control_produccion(control_id);


--
-- TOC entry 5303 (class 2606 OID 38781)
-- Name: perdidas control_produccion_perdidas_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas
    ADD CONSTRAINT control_produccion_perdidas_fk FOREIGN KEY (control_id) REFERENCES public.control_produccion(control_id);


--
-- TOC entry 5350 (class 2606 OID 38696)
-- Name: costo_detalle_produccion costo_produccion_costo_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_detalle_produccion
    ADD CONSTRAINT costo_produccion_costo_detalle_produccion_fk FOREIGN KEY (costo_id) REFERENCES public.costo_produccion(costo_id);


--
-- TOC entry 5269 (class 2606 OID 38481)
-- Name: ajustes deposito_ajustes_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes
    ADD CONSTRAINT deposito_ajustes_fk FOREIGN KEY (deposito_id) REFERENCES public.deposito(deposito_id);


--
-- TOC entry 5312 (class 2606 OID 38486)
-- Name: nota_remision_compra deposito_nota_remision_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT deposito_nota_remision_compra_fk FOREIGN KEY (deposito_id) REFERENCES public.deposito(deposito_id);


--
-- TOC entry 5250 (class 2606 OID 66058)
-- Name: pedido_materia_produccion deposito_pedido_materia_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_produccion
    ADD CONSTRAINT deposito_pedido_materia_produccion_fk FOREIGN KEY (deposito_id) REFERENCES public.deposito(deposito_id);


--
-- TOC entry 5332 (class 2606 OID 66175)
-- Name: productos_terminados_detalle deposito_productos_terminados_det_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos_terminados_detalle
    ADD CONSTRAINT deposito_productos_terminados_det_fk FOREIGN KEY (deposito_id) REFERENCES public.deposito(deposito_id);


--
-- TOC entry 5279 (class 2606 OID 38501)
-- Name: reposicion_materia deposito_reposicion_materia_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia
    ADD CONSTRAINT deposito_reposicion_materia_fk FOREIGN KEY (deposito_id) REFERENCES public.deposito(deposito_id);


--
-- TOC entry 5366 (class 2606 OID 38491)
-- Name: stock_materia_prima deposito_stock_materia_prima_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_materia_prima
    ADD CONSTRAINT deposito_stock_materia_prima_fk FOREIGN KEY (deposito_id) REFERENCES public.deposito(deposito_id);


--
-- TOC entry 5344 (class 2606 OID 38496)
-- Name: stock_producto deposito_stock_producto_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_producto
    ADD CONSTRAINT deposito_stock_producto_fk FOREIGN KEY (deposito_id) REFERENCES public.deposito(deposito_id);


--
-- TOC entry 5261 (class 2606 OID 38431)
-- Name: equipo_detalle equipos_produccion_equipo_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipo_detalle
    ADD CONSTRAINT equipos_produccion_equipo_detalle_fk FOREIGN KEY (equipo_id) REFERENCES public.equipos_produccion(equipo_id);


--
-- TOC entry 5297 (class 2606 OID 66154)
-- Name: control_produccion etapa_control_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion
    ADD CONSTRAINT etapa_control_produccion_fk FOREIGN KEY (etapa_id) REFERENCES public.etapa_produccion(etapa_id);


--
-- TOC entry 5328 (class 2606 OID 38421)
-- Name: etapa_detalle_produccion etapa_produccion_etapa_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.etapa_detalle_produccion
    ADD CONSTRAINT etapa_produccion_etapa_detalle_produccion_fk FOREIGN KEY (etapa_id) REFERENCES public.etapa_produccion(etapa_id);


--
-- TOC entry 5258 (class 2606 OID 66128)
-- Name: trabajadores etapa_trabajadores_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trabajadores
    ADD CONSTRAINT etapa_trabajadores_fk FOREIGN KEY (id_etapa) REFERENCES public.etapa_produccion(etapa_id);


--
-- TOC entry 5271 (class 2606 OID 44157)
-- Name: cuentas_pagar factura_compra_cuentas_pagar_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT factura_compra_cuentas_pagar_fk FOREIGN KEY (id_factura_compra) REFERENCES public.factura_compra(id_factura_compra);


--
-- TOC entry 5364 (class 2606 OID 38826)
-- Name: factura_detalle_compra factura_compra_detalle_registro_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_detalle_compra
    ADD CONSTRAINT factura_compra_detalle_registro_compra_fk FOREIGN KEY (id_factura_compra) REFERENCES public.factura_compra(id_factura_compra);


--
-- TOC entry 5310 (class 2606 OID 38836)
-- Name: iva_compra factura_compra_iva_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.iva_compra
    ADD CONSTRAINT factura_compra_iva_compra_fk FOREIGN KEY (id_factura_compra) REFERENCES public.factura_compra(id_factura_compra);


--
-- TOC entry 5313 (class 2606 OID 38831)
-- Name: nota_remision_compra factura_compra_nota_remision_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT factura_compra_nota_remision_compra_fk FOREIGN KEY (id_factura_compra) REFERENCES public.factura_compra(id_factura_compra);


--
-- TOC entry 5376 (class 2606 OID 39052)
-- Name: bitacora fk_bitacora_usuario; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bitacora
    ADD CONSTRAINT fk_bitacora_usuario FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 5237 (class 2606 OID 44200)
-- Name: personal fk_personal_sucursal; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal
    ADD CONSTRAINT fk_personal_sucursal FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5377 (class 2606 OID 44092)
-- Name: historial_productos historial_productos_productos_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historial_productos
    ADD CONSTRAINT historial_productos_productos_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id) ON DELETE CASCADE;


--
-- TOC entry 5378 (class 2606 OID 44097)
-- Name: historial_productos historial_productos_usuarios_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.historial_productos
    ADD CONSTRAINT historial_productos_usuarios_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5294 (class 2606 OID 38521)
-- Name: control_calidad_produccion inspectores_control_calidad_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_produccion
    ADD CONSTRAINT inspectores_control_calidad_produccion_fk FOREIGN KEY (id_inspectores) REFERENCES public.inspectores(id_inspectores);


--
-- TOC entry 5298 (class 2606 OID 38516)
-- Name: control_produccion inspectores_control_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion
    ADD CONSTRAINT inspectores_control_produccion_fk FOREIGN KEY (id_inspectores) REFERENCES public.inspectores(id_inspectores);


--
-- TOC entry 5386 (class 2606 OID 66170)
-- Name: control_produccion_consumo materia_prima_control_consumo_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion_consumo
    ADD CONSTRAINT materia_prima_control_consumo_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5351 (class 2606 OID 39026)
-- Name: costo_detalle_produccion materia_prima_costo_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_detalle_produccion
    ADD CONSTRAINT materia_prima_costo_detalle_produccion_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5357 (class 2606 OID 39011)
-- Name: pedido_detalle_compra materia_prima_pedido_detalle_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_detalle_compra
    ADD CONSTRAINT materia_prima_pedido_detalle_compra_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5355 (class 2606 OID 39016)
-- Name: pedido_materia_detalle_produccion materia_prima_pedido_materia_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_detalle_produccion
    ADD CONSTRAINT materia_prima_pedido_materia_detalle_produccion_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5353 (class 2606 OID 39021)
-- Name: reposicion_materia_detalle materia_prima_reposicion_materia_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia_detalle
    ADD CONSTRAINT materia_prima_reposicion_materia_detalle_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5239 (class 2606 OID 38531)
-- Name: usuarios modulos_usuarios_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT modulos_usuarios_fk FOREIGN KEY (modulo_id) REFERENCES public.modulos(modulo_id);


--
-- TOC entry 5370 (class 2606 OID 38876)
-- Name: ajustes_detalle motivo_detalle_ajuste_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes_detalle
    ADD CONSTRAINT motivo_detalle_ajuste_fk FOREIGN KEY (id_motivo) REFERENCES public.motivo(id_motivo);


--
-- TOC entry 5319 (class 2606 OID 38881)
-- Name: nota_compra motivo_nota_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_compra
    ADD CONSTRAINT motivo_nota_compra_fk FOREIGN KEY (id_motivo) REFERENCES public.motivo(id_motivo);


--
-- TOC entry 5320 (class 2606 OID 44170)
-- Name: nota_compra nota_compra_id_factura_compra_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_compra
    ADD CONSTRAINT nota_compra_id_factura_compra_fkey FOREIGN KEY (id_factura_compra) REFERENCES public.factura_compra(id_factura_compra);


--
-- TOC entry 5362 (class 2606 OID 38896)
-- Name: nota_detalle_compra nota_credi_compra_detalle_credito_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_detalle_compra
    ADD CONSTRAINT nota_credi_compra_detalle_credito_compra_fk FOREIGN KEY (id_nota_compra) REFERENCES public.nota_compra(id_nota_compra);


--
-- TOC entry 5314 (class 2606 OID 44181)
-- Name: nota_remision_compra nota_remision_compra_orden_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT nota_remision_compra_orden_compra_fk FOREIGN KEY (id_orden_compra) REFERENCES public.orden_de_compra(id_orden_compra);


--
-- TOC entry 5360 (class 2606 OID 38841)
-- Name: nota_remision_detalle_compra nota_remision_detalle_nota_remision_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_detalle_compra
    ADD CONSTRAINT nota_remision_detalle_nota_remision_fk FOREIGN KEY (id_nota_remision) REFERENCES public.nota_remision_compra(id_nota_remision);


--
-- TOC entry 5373 (class 2606 OID 38716)
-- Name: orden_detalle_compra orden_de_compra_detalle_orden_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_detalle_compra
    ADD CONSTRAINT orden_de_compra_detalle_orden_compra_fk FOREIGN KEY (id_orden_compra) REFERENCES public.orden_de_compra(id_orden_compra);


--
-- TOC entry 5307 (class 2606 OID 38721)
-- Name: factura_compra orden_de_compra_factura_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_compra
    ADD CONSTRAINT orden_de_compra_factura_compra_fk FOREIGN KEY (id_orden_compra) REFERENCES public.orden_de_compra(id_orden_compra);


--
-- TOC entry 5299 (class 2606 OID 38746)
-- Name: control_produccion orden_produccion_control_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion
    ADD CONSTRAINT orden_produccion_control_produccion_fk FOREIGN KEY (orden_id) REFERENCES public.orden_produccion(orden_id);


--
-- TOC entry 5277 (class 2606 OID 66106)
-- Name: costo_produccion orden_produccion_costo_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_produccion
    ADD CONSTRAINT orden_produccion_costo_produccion_fk FOREIGN KEY (orden_id) REFERENCES public.orden_produccion(orden_id);


--
-- TOC entry 5245 (class 2606 OID 66095)
-- Name: equipos_produccion orden_produccion_equipos_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipos_produccion
    ADD CONSTRAINT orden_produccion_equipos_fk FOREIGN KEY (orden_id) REFERENCES public.orden_produccion(orden_id);


--
-- TOC entry 5337 (class 2606 OID 38741)
-- Name: orden_detalle_produccion orden_produccion_orden_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_detalle_produccion
    ADD CONSTRAINT orden_produccion_orden_detalle_produccion_fk FOREIGN KEY (orden_id) REFERENCES public.orden_produccion(orden_id);


--
-- TOC entry 5292 (class 2606 OID 38751)
-- Name: producto_terminado orden_produccion_producto_terminado_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.producto_terminado
    ADD CONSTRAINT orden_produccion_producto_terminado_fk FOREIGN KEY (orden_id) REFERENCES public.orden_produccion(orden_id);


--
-- TOC entry 5342 (class 2606 OID 38971)
-- Name: control_calidad_detalle parametros_control_control_calidad_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_detalle
    ADD CONSTRAINT parametros_control_control_calidad_detalle_fk FOREIGN KEY (parametro_id) REFERENCES public.parametros_control(parametro_id);


--
-- TOC entry 5356 (class 2606 OID 38466)
-- Name: pedido_materia_detalle_produccion pedido_materia_produccion_pedido_materia_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_detalle_produccion
    ADD CONSTRAINT pedido_materia_produccion_pedido_materia_detalle_produccion_fk FOREIGN KEY (id_pedido_mat_prod) REFERENCES public.pedido_materia_produccion(id_pedido_mat_prod);


--
-- TOC entry 5280 (class 2606 OID 66065)
-- Name: reposicion_materia pedido_materia_reposicion_materia_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia
    ADD CONSTRAINT pedido_materia_reposicion_materia_fk FOREIGN KEY (id_pedido_mat_prod) REFERENCES public.pedido_materia_produccion(id_pedido_mat_prod);


--
-- TOC entry 5290 (class 2606 OID 66045)
-- Name: orden_produccion pedido_produccion_orden_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_produccion
    ADD CONSTRAINT pedido_produccion_orden_produccion_fk FOREIGN KEY (id_pedido_produccion) REFERENCES public.pedido_produccion(id_pedido_produccion);


--
-- TOC entry 5383 (class 2606 OID 66033)
-- Name: pedido_detalle_produccion pedido_produccion_pedido_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_detalle_produccion
    ADD CONSTRAINT pedido_produccion_pedido_detalle_produccion_fk FOREIGN KEY (id_pedido_produccion) REFERENCES public.pedido_produccion(id_pedido_produccion) ON DELETE CASCADE;


--
-- TOC entry 5358 (class 2606 OID 38681)
-- Name: pedido_detalle_compra pedidos_compra_pedido_detalle_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_detalle_compra
    ADD CONSTRAINT pedidos_compra_pedido_detalle_compra_fk FOREIGN KEY (id_pedido_compra) REFERENCES public.pedidos_compra(id_pedido_compra);


--
-- TOC entry 5282 (class 2606 OID 38676)
-- Name: presupuesto_compra pedidos_compra_presupuesto_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_compra
    ADD CONSTRAINT pedidos_compra_presupuesto_compra_fk FOREIGN KEY (id_pedido_compra) REFERENCES public.pedidos_compra(id_pedido_compra);


--
-- TOC entry 5330 (class 2606 OID 38786)
-- Name: perdidas_detalle perdidas_perdidas_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas_detalle
    ADD CONSTRAINT perdidas_perdidas_detalle_fk FOREIGN KEY (perdidas_id) REFERENCES public.perdidas(perdidas_id);


--
-- TOC entry 5256 (class 2606 OID 38151)
-- Name: inspectores personal_inspectores_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inspectores
    ADD CONSTRAINT personal_inspectores_fk FOREIGN KEY (id_personal) REFERENCES public.personal(id_personal);


--
-- TOC entry 5259 (class 2606 OID 38146)
-- Name: trabajadores personal_trabajadores_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trabajadores
    ADD CONSTRAINT personal_trabajadores_fk FOREIGN KEY (id_personal) REFERENCES public.personal(id_personal);


--
-- TOC entry 5375 (class 2606 OID 38706)
-- Name: presupuesto_detalle_compra presupuesto_compra_detalle_presu_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_detalle_compra
    ADD CONSTRAINT presupuesto_compra_detalle_presu_compra_fk FOREIGN KEY (id_presupuesto_compra) REFERENCES public.presupuesto_compra(id_presupuesto_compra);


--
-- TOC entry 5286 (class 2606 OID 38711)
-- Name: orden_de_compra presupuesto_compra_orden_de_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_de_compra
    ADD CONSTRAINT presupuesto_compra_orden_de_compra_fk FOREIGN KEY (id_presupuesto_compra) REFERENCES public.presupuesto_compra(id_presupuesto_compra);


--
-- TOC entry 5295 (class 2606 OID 38761)
-- Name: control_calidad_produccion producto_terminado_control_calidad_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_produccion
    ADD CONSTRAINT producto_terminado_control_calidad_produccion_fk FOREIGN KEY (terminado_id) REFERENCES public.producto_terminado(terminado_id);


--
-- TOC entry 5333 (class 2606 OID 38756)
-- Name: productos_terminados_detalle producto_terminado_productos_terminados_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos_terminados_detalle
    ADD CONSTRAINT producto_terminado_productos_terminados_detalle_fk FOREIGN KEY (terminado_id) REFERENCES public.producto_terminado(terminado_id);


--
-- TOC entry 5343 (class 2606 OID 38956)
-- Name: control_calidad_detalle productos_control_calidad_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_detalle
    ADD CONSTRAINT productos_control_calidad_detalle_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5336 (class 2606 OID 38946)
-- Name: control_produccion_detalle productos_control_produccion_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion_detalle
    ADD CONSTRAINT productos_control_produccion_detalle_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5300 (class 2606 OID 66149)
-- Name: control_produccion productos_control_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion
    ADD CONSTRAINT productos_control_produccion_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5329 (class 2606 OID 38966)
-- Name: etapa_detalle_produccion productos_etapa_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.etapa_detalle_produccion
    ADD CONSTRAINT productos_etapa_detalle_produccion_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5243 (class 2606 OID 66141)
-- Name: etapa_produccion productos_etapa_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.etapa_produccion
    ADD CONSTRAINT productos_etapa_produccion_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5338 (class 2606 OID 38941)
-- Name: orden_detalle_produccion productos_orden_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_detalle_produccion
    ADD CONSTRAINT productos_orden_detalle_produccion_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5339 (class 2606 OID 38916)
-- Name: parametros_control productos_parametros_control_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.parametros_control
    ADD CONSTRAINT productos_parametros_control_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5384 (class 2606 OID 66038)
-- Name: pedido_detalle_produccion productos_pedido_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_detalle_produccion
    ADD CONSTRAINT productos_pedido_detalle_produccion_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5331 (class 2606 OID 38961)
-- Name: perdidas_detalle productos_perdidas_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas_detalle
    ADD CONSTRAINT productos_perdidas_detalle_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5334 (class 2606 OID 38951)
-- Name: productos_terminados_detalle productos_productos_terminados_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos_terminados_detalle
    ADD CONSTRAINT productos_productos_terminados_detalle_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5345 (class 2606 OID 38911)
-- Name: stock_producto productos_stock_producto_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_producto
    ADD CONSTRAINT productos_stock_producto_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5325 (class 2606 OID 43493)
-- Name: productos productos_tipo_iva_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_tipo_iva_fk FOREIGN KEY (iva_id) REFERENCES public.tipo_iva(iva_id);


--
-- TOC entry 5272 (class 2606 OID 38576)
-- Name: cuentas_pagar proveedor_cuentas_pagar_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT proveedor_cuentas_pagar_fk FOREIGN KEY (id_proveedor) REFERENCES public.proveedor(id_proveedor);


--
-- TOC entry 5321 (class 2606 OID 38581)
-- Name: nota_compra proveedor_nota_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_compra
    ADD CONSTRAINT proveedor_nota_compra_fk FOREIGN KEY (id_proveedor) REFERENCES public.proveedor(id_proveedor);


--
-- TOC entry 5315 (class 2606 OID 38571)
-- Name: nota_remision_compra proveedor_nota_remision_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT proveedor_nota_remision_fk FOREIGN KEY (id_proveedor) REFERENCES public.proveedor(id_proveedor);


--
-- TOC entry 5287 (class 2606 OID 38566)
-- Name: orden_de_compra proveedor_orden_de_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_de_compra
    ADD CONSTRAINT proveedor_orden_de_compra_fk FOREIGN KEY (id_proveedor) REFERENCES public.proveedor(id_proveedor);


--
-- TOC entry 5283 (class 2606 OID 38561)
-- Name: presupuesto_compra proveedor_presupuesto_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_compra
    ADD CONSTRAINT proveedor_presupuesto_compra_fk FOREIGN KEY (id_proveedor) REFERENCES public.proveedor(id_proveedor);


--
-- TOC entry 5354 (class 2606 OID 38701)
-- Name: reposicion_materia_detalle reposicion_materia_reposicion_materia_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia_detalle
    ADD CONSTRAINT reposicion_materia_reposicion_materia_detalle_fk FOREIGN KEY (reposicion_id) REFERENCES public.reposicion_materia(reposicion_id);


--
-- TOC entry 5371 (class 2606 OID 39031)
-- Name: ajustes_detalle stock_materia_prima_ajustes_detallle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes_detalle
    ADD CONSTRAINT stock_materia_prima_ajustes_detallle_fk FOREIGN KEY (id_stock) REFERENCES public.stock_materia_prima(id_stock);


--
-- TOC entry 5273 (class 2606 OID 38646)
-- Name: cuentas_pagar sucursales_cuentas_pagar_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT sucursales_cuentas_pagar_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5246 (class 2606 OID 66100)
-- Name: equipos_produccion sucursales_equipos_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipos_produccion
    ADD CONSTRAINT sucursales_equipos_produccion_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5308 (class 2606 OID 38621)
-- Name: factura_compra sucursales_factura_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_compra
    ADD CONSTRAINT sucursales_factura_compra_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5322 (class 2606 OID 38626)
-- Name: nota_compra sucursales_nota_credi_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_compra
    ADD CONSTRAINT sucursales_nota_credi_compra_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5288 (class 2606 OID 38616)
-- Name: orden_de_compra sucursales_orden_de_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_de_compra
    ADD CONSTRAINT sucursales_orden_de_compra_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5251 (class 2606 OID 66053)
-- Name: pedido_materia_produccion sucursales_pedido_materia_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_produccion
    ADD CONSTRAINT sucursales_pedido_materia_produccion_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5380 (class 2606 OID 66028)
-- Name: pedido_produccion sucursales_pedido_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_produccion
    ADD CONSTRAINT sucursales_pedido_produccion_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5275 (class 2606 OID 38606)
-- Name: pedidos_compra sucursales_pedidos_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedidos_compra
    ADD CONSTRAINT sucursales_pedidos_compra_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5284 (class 2606 OID 38611)
-- Name: presupuesto_compra sucursales_presupuesto_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_compra
    ADD CONSTRAINT sucursales_presupuesto_compra_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5240 (class 2606 OID 38631)
-- Name: usuarios sucursales_usuarios_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT sucursales_usuarios_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5348 (class 2606 OID 38436)
-- Name: materia_prima tipo_iva_materia_prima_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.materia_prima
    ADD CONSTRAINT tipo_iva_materia_prima_fk FOREIGN KEY (iva_id) REFERENCES public.tipo_iva(iva_id);


--
-- TOC entry 5381 (class 2606 OID 66018)
-- Name: pedido_produccion tipo_pedido_pedido_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_produccion
    ADD CONSTRAINT tipo_pedido_pedido_produccion_fk FOREIGN KEY (id_tipo_pedido) REFERENCES public.tipo_pedido(id_tipo_pedido);


--
-- TOC entry 5304 (class 2606 OID 38461)
-- Name: perdidas tipo_perdida_perdidas_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas
    ADD CONSTRAINT tipo_perdida_perdidas_fk FOREIGN KEY (tipo_perdida_id) REFERENCES public.tipo_perdida(tipo_perdida_id);


--
-- TOC entry 5352 (class 2606 OID 66120)
-- Name: costo_detalle_produccion trabajadores_costo_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_detalle_produccion
    ADD CONSTRAINT trabajadores_costo_detalle_fk FOREIGN KEY (trabajadores_id) REFERENCES public.trabajadores(trabajadores_id);


--
-- TOC entry 5262 (class 2606 OID 38526)
-- Name: equipo_detalle trabajadores_equipo_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipo_detalle
    ADD CONSTRAINT trabajadores_equipo_detalle_fk FOREIGN KEY (trabajadores_id) REFERENCES public.trabajadores(trabajadores_id);


--
-- TOC entry 5326 (class 2606 OID 38906)
-- Name: productos unidad_medida_productos_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT unidad_medida_productos_fk FOREIGN KEY (id_unidad) REFERENCES public.unidad_medida(id_unidad);


--
-- TOC entry 5270 (class 2606 OID 38231)
-- Name: ajustes usuarios_ajustes_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes
    ADD CONSTRAINT usuarios_ajustes_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5268 (class 2606 OID 38236)
-- Name: cargos usuarios_cargos_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cargos
    ADD CONSTRAINT usuarios_cargos_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5324 (class 2606 OID 38271)
-- Name: unidad_medida usuarios_categoria_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.unidad_medida
    ADD CONSTRAINT usuarios_categoria_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5254 (class 2606 OID 38316)
-- Name: conductores usuarios_conductores_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.conductores
    ADD CONSTRAINT usuarios_conductores_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5296 (class 2606 OID 38396)
-- Name: control_calidad_produccion usuarios_control_calidad_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_produccion
    ADD CONSTRAINT usuarios_control_calidad_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5301 (class 2606 OID 38386)
-- Name: control_produccion usuarios_control_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion
    ADD CONSTRAINT usuarios_control_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5278 (class 2606 OID 38416)
-- Name: costo_produccion usuarios_costo_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_produccion
    ADD CONSTRAINT usuarios_costo_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5274 (class 2606 OID 38226)
-- Name: cuentas_pagar usuarios_cuentas_pagar_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT usuarios_cuentas_pagar_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5255 (class 2606 OID 38306)
-- Name: deposito usuarios_deposito_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposito
    ADD CONSTRAINT usuarios_deposito_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5247 (class 2606 OID 38381)
-- Name: equipos_produccion usuarios_equipos_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipos_produccion
    ADD CONSTRAINT usuarios_equipos_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5244 (class 2606 OID 38391)
-- Name: etapa_produccion usuarios_etapa_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.etapa_produccion
    ADD CONSTRAINT usuarios_etapa_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5309 (class 2606 OID 38201)
-- Name: factura_compra usuarios_factura_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_compra
    ADD CONSTRAINT usuarios_factura_compra_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5241 (class 2606 OID 43507)
-- Name: usuarios usuarios_id_cargo_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_id_cargo_fk FOREIGN KEY (id_cargo) REFERENCES public.cargos(id_cargo);


--
-- TOC entry 5242 (class 2606 OID 44228)
-- Name: usuarios usuarios_id_personal_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_id_personal_fk FOREIGN KEY (id_personal) REFERENCES public.personal(id_personal) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 5257 (class 2606 OID 38296)
-- Name: inspectores usuarios_inspectores_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inspectores
    ADD CONSTRAINT usuarios_inspectores_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5349 (class 2606 OID 38256)
-- Name: materia_prima usuarios_materia_prima_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.materia_prima
    ADD CONSTRAINT usuarios_materia_prima_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5263 (class 2606 OID 38286)
-- Name: modulos usuarios_modulos_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT usuarios_modulos_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5318 (class 2606 OID 38311)
-- Name: motivo usuarios_motivo_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.motivo
    ADD CONSTRAINT usuarios_motivo_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5323 (class 2606 OID 38206)
-- Name: nota_compra usuarios_nota_credi_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_compra
    ADD CONSTRAINT usuarios_nota_credi_compra_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5316 (class 2606 OID 38211)
-- Name: nota_remision_compra usuarios_nota_remision_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT usuarios_nota_remision_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5289 (class 2606 OID 38196)
-- Name: orden_de_compra usuarios_orden_de_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_de_compra
    ADD CONSTRAINT usuarios_orden_de_compra_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5291 (class 2606 OID 38376)
-- Name: orden_produccion usuarios_orden_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_produccion
    ADD CONSTRAINT usuarios_orden_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5340 (class 2606 OID 38371)
-- Name: parametros_control usuarios_parametros_control_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.parametros_control
    ADD CONSTRAINT usuarios_parametros_control_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5252 (class 2606 OID 38326)
-- Name: pedido_materia_produccion usuarios_pedido_materia_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_produccion
    ADD CONSTRAINT usuarios_pedido_materia_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5382 (class 2606 OID 66023)
-- Name: pedido_produccion usuarios_pedido_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_produccion
    ADD CONSTRAINT usuarios_pedido_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5276 (class 2606 OID 38186)
-- Name: pedidos_compra usuarios_pedidos_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedidos_compra
    ADD CONSTRAINT usuarios_pedidos_compra_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5305 (class 2606 OID 38406)
-- Name: perdidas usuarios_perdidas_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas
    ADD CONSTRAINT usuarios_perdidas_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5238 (class 2606 OID 38266)
-- Name: personal usuarios_personal_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal
    ADD CONSTRAINT usuarios_personal_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5285 (class 2606 OID 38191)
-- Name: presupuesto_compra usuarios_presupuesto_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_compra
    ADD CONSTRAINT usuarios_presupuesto_compra_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5293 (class 2606 OID 38401)
-- Name: producto_terminado usuarios_producto_terminado_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.producto_terminado
    ADD CONSTRAINT usuarios_producto_terminado_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5327 (class 2606 OID 38356)
-- Name: productos usuarios_productos_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT usuarios_productos_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5264 (class 2606 OID 38276)
-- Name: proveedor usuarios_proveedor_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.proveedor
    ADD CONSTRAINT usuarios_proveedor_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5281 (class 2606 OID 38411)
-- Name: reposicion_materia usuarios_reposicion_materia_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia
    ADD CONSTRAINT usuarios_reposicion_materia_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5367 (class 2606 OID 38366)
-- Name: stock_materia_prima usuarios_stock_materia_prima_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_materia_prima
    ADD CONSTRAINT usuarios_stock_materia_prima_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5346 (class 2606 OID 38361)
-- Name: stock_producto usuarios_stock_producto_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_producto
    ADD CONSTRAINT usuarios_stock_producto_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5266 (class 2606 OID 38251)
-- Name: sucursales usuarios_sucursales_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sucursales
    ADD CONSTRAINT usuarios_sucursales_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5267 (class 2606 OID 38241)
-- Name: timbrado usuarios_timbrado_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.timbrado
    ADD CONSTRAINT usuarios_timbrado_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5306 (class 2606 OID 38246)
-- Name: tipo_operacion usuarios_tipo_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_operacion
    ADD CONSTRAINT usuarios_tipo_compra_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5265 (class 2606 OID 38261)
-- Name: tipo_documento usuarios_tipo_documento_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_documento
    ADD CONSTRAINT usuarios_tipo_documento_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5248 (class 2606 OID 38351)
-- Name: tipo_iva usuarios_tipo_iva_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_iva
    ADD CONSTRAINT usuarios_tipo_iva_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5379 (class 2606 OID 65996)
-- Name: tipo_pedido usuarios_tipo_pedido_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_pedido
    ADD CONSTRAINT usuarios_tipo_pedido_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5249 (class 2606 OID 38331)
-- Name: tipo_perdida usuarios_tipo_perdida_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_perdida
    ADD CONSTRAINT usuarios_tipo_perdida_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5260 (class 2606 OID 38291)
-- Name: trabajadores usuarios_trabajadores_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trabajadores
    ADD CONSTRAINT usuarios_trabajadores_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5253 (class 2606 OID 38321)
-- Name: vehiculos usuarios_vehiculos_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.vehiculos
    ADD CONSTRAINT usuarios_vehiculos_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5317 (class 2606 OID 38471)
-- Name: nota_remision_compra vehiculos_nota_remision_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT vehiculos_nota_remision_compra_fk FOREIGN KEY (vehiculo_id) REFERENCES public.vehiculos(vehiculo_id);


-- Completed on 2026-05-23 09:53:32

--
-- PostgreSQL database dump complete
--

